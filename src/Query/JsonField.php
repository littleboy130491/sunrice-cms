<?php

declare(strict_types=1);

namespace Sunrice\Query;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Filtering and sorting on custom-field values inside a JSON `data`
 * column. Strings use native JSON path queries; numbers and dates use
 * driver-specific CAST expressions (sqlite/mysql/pgsql).
 */
class JsonField
{
    /** Operators accepted by where(); anything else is a programming error. */
    public const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like', 'in', 'contains', 'contains any'];

    /**
     * Field paths go into raw SQL (castExpression), so only plain handle
     * characters and dots between segments are allowed.
     */
    public static function assertSafe(string $path, ?string $operator = null): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException("Invalid field path [{$path}].");
        }
        if ($operator !== null && ! in_array(strtolower($operator), static::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator [{$operator}].");
        }
    }

    /**
     * @param  'in'|'contains'|'='|'!='|'<'|'<='|'>'|'>='|string  $operator
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function where(
        Builder $query,
        string $column,
        string $path,
        string $operator,
        mixed $value,
        ?string $cast = null,
    ): Builder {
        static::assertSafe($path, $operator);
        $operator = strtolower($operator);

        // Booleans (toggle fields) can't be cast to numbers on every
        // database: compare the JSON value itself (true, or a legacy 1).
        if (is_bool($value) && in_array($operator, ['=', '!=', '<>'], true)) {
            $match = fn (Builder $q) => static::where($q, $column, $path, 'contains any', [$value, (int) $value], $cast);

            return $operator === '=' ? $query->where($match) : $query->whereNot($match);
        }

        return match ($operator) {
            'in' => $query->where(function (Builder $q) use ($column, $path, $value, $cast): void {
                foreach ((array) $value as $v) {
                    $q->orWhere(fn (Builder $qq) => static::where($qq, $column, $path, '=', $v, $cast));
                }
            }),
            'contains' => $query->whereJsonContains($column.'->'.$path, $value),
            // A list field holding at least one of the values.
            'contains any' => $query->where(function (Builder $q) use ($column, $path, $value): void {
                foreach ((array) $value as $v) {
                    $q->orWhereJsonContains($column.'->'.$path, $v);
                }
            }),
            '!=' => $query->where(fn (Builder $q) => static::compare($q, $column, $path, '<>', $value, $cast)),
            default => $query->where(fn (Builder $q) => static::compare($q, $column, $path, $operator, $value, $cast)),
        };
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected static function compare(Builder $query, string $column, string $path, string $operator, mixed $value, ?string $cast): Builder
    {
        if ($cast === 'number' || $cast === 'date') {
            return $query->whereRaw(
                static::castExpression($query, $column, $path, $cast).' '.$operator.' ?',
                [$cast === 'date' ? static::dateValue($value) : $value + 0],
            );
        }

        if ($operator === 'like') {
            return $query->whereLike($column.'->'.$path, (string) $value);
        }

        return $query->where($column.'->'.$path, $operator, $value);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function orderBy(Builder $query, string $column, string $path, ?string $cast, string $direction = 'asc'): Builder
    {
        static::assertSafe($path);
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($cast === 'number' || $cast === 'date') {
            return $query->orderByRaw(static::castExpression($query, $column, $path, $cast).' '.$direction);
        }

        return $query->orderBy($column.'->'.$path, $direction);
    }

    /**
     * Driver-specific CAST for numeric and date comparison/sorting.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function castExpression(Builder $query, string $column, string $path, string $cast): string
    {
        static::assertSafe($path);
        $connection = $query->getConnection();
        $driver = $connection instanceof Connection
            ? $connection->getDriverName()
            : (string) config('database.default');
        $col = '"'.str_replace('.', '"."', $column).'"';
        $extract = match ($driver) {
            'pgsql' => "({$col} #>> '{".str_replace('.', ',', $path)."}')",
            'mysql', 'mariadb' => 'JSON_UNQUOTE(JSON_EXTRACT(`'.str_replace('.', '`.`', $column)."`, '$.\"{$path}\"'))",
            default => "json_extract({$col}, '$.{$path}')", // sqlite
        };

        return match ($cast) {
            'number' => "CAST({$extract} AS ".($driver === 'pgsql' ? 'DOUBLE PRECISION' : 'REAL').')',
            'date' => $driver === 'pgsql' ? "({$extract})::timestamp" : "datetime({$extract})",
            default => $extract,
        };
    }

    protected static function dateValue(mixed $value): string
    {
        $value = (string) $value;

        return strlen($value) <= 10 ? $value.' 00:00:00' : str_replace('T', ' ', substr($value, 0, 19));
    }
}

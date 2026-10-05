<?php

declare(strict_types=1);

namespace Sunrice\Query;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtering and sorting on custom-field values inside a JSON `data`
 * column. Strings use native JSON path queries; numbers and dates use
 * driver-specific CAST expressions (sqlite/mysql/pgsql).
 */
class JsonField
{
    /**
     * @param  'in'|'contains'|'='|'!='|'<'|'<='|'>'|'>='|string  $operator
     */
    public static function where(
        Builder $query,
        string $column,
        string $path,
        string $operator,
        mixed $value,
        ?string $cast = null,
    ): Builder {
        return match ($operator) {
            'in' => $query->where(function (Builder $q) use ($column, $path, $value, $cast): void {
                foreach ((array) $value as $v) {
                    $q->orWhere(fn (Builder $qq) => static::where($qq, $column, $path, '=', $v, $cast));
                }
            }),
            'contains' => $query->whereJsonContains($column.'->'.$path, $value),
            '!=' => $query->where(fn (Builder $q) => static::compare($q, $column, $path, '<>', $value, $cast)),
            default => $query->where(fn (Builder $q) => static::compare($q, $column, $path, $operator, $value, $cast)),
        };
    }

    protected static function compare(Builder $query, string $column, string $path, string $operator, mixed $value, ?string $cast): Builder
    {
        if ($cast === 'number' || $cast === 'date') {
            return $query->whereRaw(
                static::castExpression($query, $column, $path, $cast).' '.$operator.' ?',
                [$cast === 'date' ? static::dateValue($value) : $value + 0],
            );
        }

        return $query->where($column.'->'.$path, $operator, $value);
    }

    public static function orderBy(Builder $query, string $column, string $path, ?string $cast, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($cast === 'number' || $cast === 'date') {
            return $query->orderByRaw(static::castExpression($query, $column, $path, $cast).' '.$direction);
        }

        return $query->orderBy($column.'->'.$path, $direction);
    }

    /**
     * Driver-specific CAST for numeric and date comparison/sorting.
     */
    public static function castExpression(Builder $query, string $column, string $path, string $cast): string
    {
        $driver = $query->getConnection()->getDriverName();
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

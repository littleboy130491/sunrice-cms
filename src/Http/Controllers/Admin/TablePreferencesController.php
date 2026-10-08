<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Setting;

/**
 * PUT {admin}/table-preferences/{table} — stores the user's chosen
 * column set and rows per page, per table.
 */
class TablePreferencesController extends Controller
{
    public function update(Request $request, string $table): JsonResponse
    {
        $validated = $request->validate([
            'columns' => ['required_without:per_page', 'array', 'max:50'],
            'columns.*' => ['string', 'max:100', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'per_page' => ['required_without:columns', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $userId = $request->user(config('sunrice.auth.guard'))->getAuthIdentifier();
        if (isset($validated['columns'])) {
            Setting::set("table_columns.{$userId}.{$table}", $validated['columns']);
        }
        if (isset($validated['per_page'])) {
            Setting::set("table_per_page.{$userId}.{$table}", (int) $validated['per_page']);
        }

        return response()->json(array_intersect_key($validated, array_flip(['columns', 'per_page'])));
    }

    public const MAX_PER_PAGE = 100;

    /**
     * Rows per page for a table: ?per_page= when given, else the user's
     * saved choice, else the default. Always 1–100.
     */
    public static function perPageFor(Request $request, string $table, int $default): int
    {
        $perPage = $request->query('per_page');
        if (! is_numeric($perPage)) {
            $userId = $request->user(config('sunrice.auth.guard'))?->getAuthIdentifier();
            $saved = $userId === null ? null : Setting::get("table_per_page.{$userId}.{$table}");
            $perPage = is_numeric($saved) ? $saved : $default;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $perPage));
    }

    /**
     * Columns the user picked for a table (or the defaults).
     *
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    public static function columnsFor(int $userId, string $table, array $default): array
    {
        $columns = Setting::get("table_columns.{$userId}.{$table}");

        return is_array($columns) && $columns !== [] ? array_values($columns) : $default;
    }
}

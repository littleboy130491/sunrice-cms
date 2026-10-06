<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Models\Setting;

/**
 * PUT {admin}/table-preferences/{table} — stores the user's chosen
 * column set per table.
 */
class TablePreferencesController extends Controller
{
    public function update(Request $request, string $table): JsonResponse
    {
        $validated = $request->validate([
            'columns' => ['required', 'array', 'max:50'],
            'columns.*' => ['string', 'max:100', 'regex:/^[a-zA-Z0-9_.-]+$/'],
        ]);

        $userId = $request->user(config('sunrice.auth.guard'))->getAuthIdentifier();
        Setting::set("table_columns.{$userId}.{$table}", $validated['columns']);

        return response()->json(['columns' => $validated['columns']]);
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

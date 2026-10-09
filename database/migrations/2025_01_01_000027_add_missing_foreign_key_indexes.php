<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL indexes every foreign key column by itself; Postgres and
     * SQLite don't, so lookups like "the entries of this term" scanned the
     * whole table there. Columns that already have an index are skipped.
     *
     * @var array<string, list<string>>
     */
    protected array $columns = [
        'sunrice_entry_term' => ['term_id'],
        'sunrice_entries' => ['blueprint_id'],
        'sunrice_terms' => ['taxonomy_id', 'parent_id'],
        'sunrice_revisions' => ['entry_translation_id'],
        'sunrice_redirects' => ['entry_id'],
        'sunrice_collection_taxonomy' => ['taxonomy_id'],
        'sunrice_menu_items' => ['parent_id'],
        'sunrice_asset_folders' => ['parent_id'],
        'sunrice_assets' => ['folder_id'],
        'sunrice_form_submissions' => ['form_id'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            $missing = array_values(array_filter($columns, fn (string $column) => ! $this->indexed($table, $column)));
            if ($missing === []) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
                foreach ($missing as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    /** Whether an index starts with this column (so it can serve lookups by it). */
    protected function indexed(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }
};

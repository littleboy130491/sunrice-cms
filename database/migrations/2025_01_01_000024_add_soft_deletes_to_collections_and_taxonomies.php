<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a collection or taxonomy keeps its content (hidden):
     * re-creating one with the same handle brings it back, and
     * `sunrice:orphans --purge` removes it for good.
     */
    public function up(): void
    {
        Schema::table('sunrice_collections', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('sunrice_taxonomies', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_collections', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('sunrice_taxonomies', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};

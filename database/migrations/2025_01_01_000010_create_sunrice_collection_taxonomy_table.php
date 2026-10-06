<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_collection_taxonomy', function (Blueprint $table): void {
            $table->foreignId('collection_id')->constrained('sunrice_collections')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('sunrice_taxonomies')->cascadeOnDelete();

            $table->primary(['collection_id', 'taxonomy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_collection_taxonomy');
    }
};

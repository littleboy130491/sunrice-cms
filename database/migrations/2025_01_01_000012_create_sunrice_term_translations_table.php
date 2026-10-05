<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_term_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('term_id')->constrained('sunrice_terms')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('sunrice_taxonomies')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->string('slug');
            $table->json('data');
            $table->timestamps();

            $table->unique(['term_id', 'locale']);
            $table->unique(['taxonomy_id', 'locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_term_translations');
    }
};

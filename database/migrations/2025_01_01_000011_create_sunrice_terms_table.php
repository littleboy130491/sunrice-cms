<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('sunrice_taxonomies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sunrice_terms')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_terms');
    }
};

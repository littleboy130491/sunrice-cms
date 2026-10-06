<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_entry_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('sunrice_entries')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('sunrice_collections')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->string('slug');
            $table->json('data');
            $table->json('seo');
            $table->json('draft')->nullable();
            $table->boolean('is_ready')->default(false);
            $table->timestamp('content_published_at')->nullable();
            $table->timestamps();

            $table->unique(['entry_id', 'locale']);
            $table->unique(['collection_id', 'locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_entry_translations');
    }
};

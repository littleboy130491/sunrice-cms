<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('sunrice_collections')->cascadeOnDelete();
            $table->foreignId('blueprint_id')->nullable()->constrained('sunrice_blueprints')->restrictOnDelete();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->string('template')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable(); // reserved
            $table->timestamps();
            $table->softDeletes();

            $table->index(['collection_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_entries');
    }
};

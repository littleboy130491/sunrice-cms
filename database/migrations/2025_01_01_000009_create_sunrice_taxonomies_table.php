<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('handle')->unique();
            $table->string('title');
            $table->foreignId('blueprint_id')->nullable()->constrained('sunrice_blueprints')->restrictOnDelete();
            $table->boolean('hierarchical')->default(false);
            $table->json('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_taxonomies');
    }
};

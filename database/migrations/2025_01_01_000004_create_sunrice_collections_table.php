<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('handle')->unique();
            $table->string('title');
            $table->foreignId('blueprint_id')->nullable()->constrained('sunrice_blueprints')->restrictOnDelete();
            $table->json('settings');
            $table->json('archive_data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_collections');
    }
};

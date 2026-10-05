<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_global_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('global_id')->constrained('sunrice_globals')->cascadeOnDelete();
            $table->string('locale', 10)->nullable(); // null when not translatable
            $table->json('data');
            $table->timestamps();

            $table->unique(['global_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_global_values');
    }
};

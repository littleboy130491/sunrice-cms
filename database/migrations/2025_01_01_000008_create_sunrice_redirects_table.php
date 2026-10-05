<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_path');
            $table->string('locale', 10);
            $table->foreignId('entry_id')->constrained('sunrice_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['old_path', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_redirects');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_translation_id')->constrained('sunrice_entry_translations')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('content');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_revisions');
    }
};

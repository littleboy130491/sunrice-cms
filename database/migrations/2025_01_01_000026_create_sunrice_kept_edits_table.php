<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unsaved changes of an editor whose editing was taken over by someone
     * else, kept so they can be loaded back instead of being lost.
     */
    public function up(): void
    {
        Schema::create('sunrice_kept_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->unsignedBigInteger('model_id');
            $table->string('locale', 10)->default('');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('taken_by')->nullable();
            $table->json('content');
            $table->timestamps();
            $table->index(['type', 'model_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_kept_edits');
    }
};

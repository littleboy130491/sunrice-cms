<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who changed what in the admin, and when: entries, terms, structure,
     * menus, globals, assets, forms, users, roles and settings.
     */
    public function up(): void
    {
        Schema::create('sunrice_activity_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // Kept as written, so the log still reads well after the user is deleted.
            $table->string('user_name')->nullable();
            $table->string('action', 30)->index();
            $table->string('subject_type', 40)->index();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_activity_log');
    }
};

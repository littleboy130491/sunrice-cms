<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('sunrice_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sunrice_menu_items')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('type'); // entry | collection | term | url
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url')->nullable();
            $table->json('labels'); // {locale: label}
            $table->boolean('new_tab')->default(false);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_menu_items');
    }
};

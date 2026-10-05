<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('owner_id')->nullable();
            $table->timestamps();
        });
    }
};

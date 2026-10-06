<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunrice_entry_term', function (Blueprint $table): void {
            $table->foreignId('entry_id')->constrained('sunrice_entries')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('sunrice_terms')->cascadeOnDelete();

            $table->primary(['entry_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunrice_entry_term');
    }
};

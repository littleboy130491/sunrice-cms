<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terms get their own template (overriding the taxonomy's) and,
     * per language, SEO fields like entries.
     */
    public function up(): void
    {
        Schema::table('sunrice_terms', function (Blueprint $table): void {
            $table->string('template')->nullable();
        });

        Schema::table('sunrice_term_translations', function (Blueprint $table): void {
            $table->json('seo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sunrice_terms', function (Blueprint $table): void {
            $table->dropColumn('template');
        });

        Schema::table('sunrice_term_translations', function (Blueprint $table): void {
            $table->dropColumn('seo');
        });
    }
};

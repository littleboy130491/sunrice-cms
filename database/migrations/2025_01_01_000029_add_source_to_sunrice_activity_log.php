<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a change came from: the admin, an AI agent (and which access
     * token), or the system (console, queue).
     */
    public function up(): void
    {
        Schema::table('sunrice_activity_log', function (Blueprint $table): void {
            $table->string('source', 20)->default('admin')->index();
            $table->string('via')->nullable();
        });
        DB::table('sunrice_activity_log')->whereNull('user_id')->update(['source' => 'system']);
    }

    public function down(): void
    {
        Schema::table('sunrice_activity_log', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'via']);
        });
    }
};

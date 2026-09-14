<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Null for on-page findings (source=synthseo, never strategy-specific)
 * and for any existing lighthouse finding recorded before this column
 * existed - those were always mobile in practice, but backfilling a
 * historical row with a value it was never actually tagged with isn't
 * a fact, so they stay null rather than guessed at. Only findings
 * generated after this ships are ever explicitly 'mobile' or
 * 'desktop'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->string('strategy')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->dropColumn('strategy');
        });
    }
};

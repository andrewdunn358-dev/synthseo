<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `audit_frequency` of 'off' is the default deliberately - adding a
 * site should never start silently consuming API/PSI calls for it. A
 * client (or Frankie) opts a site into recurring audits explicitly.
 *
 * `next_audit_at` is a plain timestamp checked by the scheduler rather
 * than computing "is it due" from frequency + last audit's date every
 * time - a site can be due once and only once even if the scheduler
 * command overlaps its own run, because being due is a fact stored on
 * the row, not derived fresh each check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('audit_frequency')->default('off')->after('url');
            $table->timestamp('next_audit_at')->nullable()->after('audit_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['audit_frequency', 'next_audit_at']);
        });
    }
};

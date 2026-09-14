<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cms` is auto-detected from audit crawls (see
 * SeoAuditService::detectCms) - null until the first successful
 * audit, never a guess. `host` is set by hand: hosting providers are
 * not reliably detectable from outside, and Frankie already knows
 * which of his clients sit on which hosting, so this is one field to
 * fill in rather than infrastructure to build for something a human
 * already has the answer to.
 *
 * Both exist for one purpose: feeding the AI recommendations prompt
 * enough context to give platform-specific instructions ("install a
 * WordPress caching plugin") instead of generic ones ("enable
 * caching") - see GenerateAuditRecommendations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('cms')->nullable()->after('audit_frequency');
            $table->string('host')->nullable()->after('cms');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['cms', 'host']);
        });
    }
};

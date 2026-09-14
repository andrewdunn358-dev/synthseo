<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The existing lh_* flat columns have always meant "mobile" in
 * practice - every audit run so far has only ever called PageSpeed
 * with strategy=mobile - but nothing said so explicitly. Rather than
 * duplicate ten more flat columns for desktop (or restructure the
 * existing ones and risk a migration against real audit data this
 * late), desktop results land in one JSON column with the same shape:
 * {scores: {...4 category scores}, metrics: {lcp_ms, tbt_ms, cls},
 * final_url, error}. Nullable throughout, same reasoning as the
 * original lighthouse migration - a null means "no result", never a
 * zero standing in for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->json('lighthouse_desktop')->nullable()->after('lighthouse_error');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('lighthouse_desktop');
        });
    }
};

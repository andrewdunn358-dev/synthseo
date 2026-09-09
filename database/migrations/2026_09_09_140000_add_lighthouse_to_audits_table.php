<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google PageSpeed Insights results, stored alongside our own checks.
 *
 * Nullable throughout, and that is the point: PSI is a third-party call
 * that fails for reasons that have nothing to do with the client's site
 * (rate limits, no API key configured, Google unable to reach the
 * host). A null here means "we did not get a Lighthouse result", which
 * the UI reports as exactly that rather than as a zero. Storing 0 for a
 * failed lookup would tell a client their performance is catastrophic
 * when in fact nobody measured it.
 *
 * `lighthouse_strategy` records mobile or desktop because the two give
 * materially different numbers, and a report that does not say which it
 * ran is not reproducible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedTinyInteger('lh_performance')->nullable()->after('score');
            $table->unsignedTinyInteger('lh_seo')->nullable()->after('lh_performance');
            $table->unsignedTinyInteger('lh_accessibility')->nullable()->after('lh_seo');
            $table->unsignedTinyInteger('lh_best_practices')->nullable()->after('lh_accessibility');
            $table->unsignedInteger('lh_lcp_ms')->nullable()->after('lh_best_practices');
            $table->unsignedInteger('lh_tbt_ms')->nullable()->after('lh_lcp_ms');
            $table->decimal('lh_cls', 6, 3)->nullable()->after('lh_tbt_ms');
            $table->string('lighthouse_strategy')->nullable()->after('lh_cls');
            // The URL Lighthouse actually measured. It follows redirects,
            // and on this project's first real test the requested page
            // redirected to a different one - a report that quietly
            // audits somewhere else is worse than no report.
            $table->string('lighthouse_final_url')->nullable()->after('lighthouse_strategy');
            $table->text('lighthouse_error')->nullable()->after('lighthouse_final_url');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn([
                'lh_performance', 'lh_seo', 'lh_accessibility', 'lh_best_practices',
                'lh_lcp_ms', 'lh_tbt_ms', 'lh_cls', 'lighthouse_strategy',
                'lighthouse_final_url', 'lighthouse_error',
            ]);
        });
    }
};

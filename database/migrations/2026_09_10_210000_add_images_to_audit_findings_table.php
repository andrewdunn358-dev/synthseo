<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lighthouse's own web UI shows real thumbnail previews of specific
 * offending images for checks like "Properly size images" - not from
 * separate embedded thumbnail data, just the real image URL rendered
 * directly. That URL (plus size/savings) sits in the audit's own
 * details.items array in the PageSpeed API response, which
 * PageSpeedService was discarding entirely - only the generic
 * top-level "Improve image delivery" summary was kept. This column
 * is where the specific items now land, nullable because only a
 * handful of Lighthouse checks are image checks with this shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->json('images')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};

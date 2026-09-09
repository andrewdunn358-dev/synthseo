<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which engine produced a finding.
 *
 * Worth recording rather than merging the two silently: Lighthouse's
 * opportunities are measured in a headless Chrome with network
 * throttling, ours are structural checks on the raw HTML. Presenting
 * them as one undifferentiated list would imply they carry the same
 * kind of evidence, and a client asking "how do you know" deserves a
 * straight answer either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->string('source')->default('synthseo')->after('audit_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

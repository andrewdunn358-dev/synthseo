<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Created lazily on first newsletter action, not up front - a site
 * that never sends a newsletter should never have an empty Resend
 * Audience sitting unused on their account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('resend_audience_id')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('resend_audience_id');
        });
    }
};

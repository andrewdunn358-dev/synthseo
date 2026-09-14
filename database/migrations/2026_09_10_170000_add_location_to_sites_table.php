<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set by hand, same reasoning as `host` - a business's actual town
 * isn't reliably extractable from a homepage (it's often only on a
 * Contact page, or not stated at all), and Frankie already knows
 * where every client is. What genuinely IS derivable from the page
 * itself is the business category, which is what
 * ClaudeContentService::inferBusinessCategory reads from the site's
 * own content rather than asking for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('location')->nullable()->after('host');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};

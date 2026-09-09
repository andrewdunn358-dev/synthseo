<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recommendations are generated on request, not automatically with
 * every audit - a client who never opens the report shouldn't cost an
 * API call. `recommendations_status` follows the same
 * queued/generating/completed/failed shape as audits.status itself,
 * kept separate so an audit can be `completed` while its
 * recommendations are still `generating` - two independent things
 * finishing at different times, not one blocking the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->text('recommendations')->nullable()->after('error');
            $table->string('recommendations_status')->nullable()->after('recommendations');
            $table->text('recommendations_error')->nullable()->after('recommendations_status');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['recommendations', 'recommendations_status', 'recommendations_error']);
        });
    }
};

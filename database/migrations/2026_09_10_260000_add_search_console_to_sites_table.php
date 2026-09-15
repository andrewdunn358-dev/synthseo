<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site Search Console connection. Tokens live on the site rather
 * than the account because whose Google login grants access varies -
 * sometimes Frankie's own account has the client's property shared
 * with it, sometimes the client connects their own. Per-site works
 * for both without needing to model that difference.
 *
 * Both token columns are cast encrypted on the model (see Site) - a
 * refresh token is a long-lived key to a client's real search data,
 * and storing that in plain text alongside everything else in this
 * database would be careless.
 *
 * gsc_property is Google's own identifier for the property, not our
 * site URL: domain properties look like "sc-domain:example.co.uk",
 * URL-prefix properties like "https://example.co.uk/". They are not
 * interchangeable and neither can be derived reliably from the other,
 * which is why the connect flow asks Google which properties this
 * login can actually see rather than guessing at a format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('gsc_access_token')->nullable()->after('resend_audience_id');
            $table->text('gsc_refresh_token')->nullable()->after('gsc_access_token');
            $table->timestamp('gsc_token_expires_at')->nullable()->after('gsc_refresh_token');
            $table->string('gsc_property')->nullable()->after('gsc_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['gsc_access_token', 'gsc_refresh_token', 'gsc_token_expires_at', 'gsc_property']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverses an earlier deliberate decision (see the site_user-era
 * ResendService doc comment) to keep subscriber PII out of this app
 * entirely and let Resend's own Audiences be the one source of
 * truth. That assumed a Full-access API key; the real account this
 * shipped against only has Sending access, which cannot manage
 * Audiences/Contacts/Broadcasts at all - only the plain send-email
 * endpoint. Since this newsletter feature is still being tried out
 * and may not stick, the simplest working path is a local list and
 * one send call per subscriber, not a bigger rearchitecture toward
 * Full access this account may never get.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};

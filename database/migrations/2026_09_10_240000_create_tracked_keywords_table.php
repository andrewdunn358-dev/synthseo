<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per keyword a site wants tracked, e.g. "estate agents North
 * Shields". The actual rank history lives in keyword_rankings, kept
 * separate so a keyword tracked for months builds up a real trend
 * (same before/after value a client can point to) rather than this
 * table only ever holding the latest check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->timestamp('next_check_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_keywords');
    }
};

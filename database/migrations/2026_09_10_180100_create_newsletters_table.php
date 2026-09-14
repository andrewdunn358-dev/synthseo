<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generation and sending are two deliberately separate steps, not one
 * action - same "recommendation-only until a human reviews it"
 * philosophy as content_pieces and social_posts. A newsletter is drafted
 * (queued/generating/completed/failed), then a person reads it and
 * clicks send as a second, explicit action. Nothing in this app emails
 * a client's subscriber list without someone having read the content
 * first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('status')->default('queued');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('model')->nullable();
            $table->text('error')->nullable();
            $table->string('resend_broadcast_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};

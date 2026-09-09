<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One AI-generated draft against one site.
 *
 * account_id is denormalised here for the same reason it is on audits -
 * the tenant scope filters without a join, and a content row can never
 * end up visible to the wrong tenant because its site was reassigned.
 *
 * `status` mirrors the audit lifecycle (queued/generating/completed/
 * failed) rather than just checking whether `body` is filled in - a
 * generation that failed partway should say so, not look like an empty
 * draft nobody got round to writing.
 *
 * v1 is recommendation-only: nothing here ever posts anywhere on its
 * own. This table is the queue of drafts a client or Frankie reviews
 * and copies out by hand. Auto-posting is a v2 problem for a different
 * table, once this is proven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('article');
            $table->string('topic');
            $table->string('status')->default('queued');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pieces');
    }
};

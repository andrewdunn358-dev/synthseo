<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A social post has two independent pieces - caption (Claude) and
 * image (OpenAI) - each with its own success/failure, stored
 * separately rather than as one pass/fail. A caption without an image
 * that failed to generate is still something a client can copy and
 * use; collapsing both into one status would throw away a genuinely
 * usable half of the result over the half that didn't work.
 *
 * v1 is recommendation-only, same as content_pieces - nothing here
 * ever posts anywhere on its own. This is a queue of drafts a client
 * or Frankie reviews and posts by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('general');
            $table->string('topic');
            $table->string('status')->default('queued');
            $table->text('caption')->nullable();
            $table->text('caption_error')->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_error')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};

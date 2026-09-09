<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One run of the audit engine against one site.
 *
 * account_id is denormalised onto this table even though it could be
 * reached through sites. That is deliberate: the tenant scope then
 * filters audits without a join, and an audit can never be silently
 * visible to the wrong tenant because its site row was reassigned.
 *
 * `status` carries queued/running/completed/failed rather than being
 * inferred from finished_at being null - a job that dies mid-run leaves
 * finished_at null forever, and "still running" and "crashed three days
 * ago" should not look the same to a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};

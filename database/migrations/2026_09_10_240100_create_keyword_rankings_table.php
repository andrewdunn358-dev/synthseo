<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per check, ever - the history a trend line is built from.
 * rank nullable and meaningfully so: null means the site wasn't found
 * in the top 100 results checked, a real and common outcome, never
 * conflated with an actual API failure (which is what `error` is for).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_keyword_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at');

            $table->index(['tracked_keyword_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_rankings');
    }
};

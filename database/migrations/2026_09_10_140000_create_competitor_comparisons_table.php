<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One comparison of a site's organic search footprint against one
 * named competitor domain, via DataForSEO Labs' domain_rank_overview.
 *
 * Traffic and keyword count only for v1 - domain authority/backlink
 * comparison would need the separate Backlinks API, a second vendor
 * integration for later, not bundled in here just because it is
 * "more competitor data". Two clean numbers a client can actually
 * read beat five they cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('competitor_domain');
            $table->string('status')->default('queued');
            $table->unsignedBigInteger('our_traffic')->nullable();
            $table->unsignedInteger('our_keywords')->nullable();
            $table->unsignedBigInteger('competitor_traffic')->nullable();
            $table->unsignedInteger('competitor_keywords')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_comparisons');
    }
};

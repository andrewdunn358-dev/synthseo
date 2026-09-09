<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One check result from one audit.
 *
 * PASSES ARE STORED, not just failures. It costs a few rows and it buys
 * two things a client-facing report needs: a report that says what is
 * right as well as what is wrong, and an honest distinction between
 * "this check passed" and "this check never ran" - which matters the
 * moment a crawl half-fails and someone asks why an issue disappeared.
 *
 * `value` holds what was actually measured (the title that was found,
 * the number of images missing alt text) so a report can quote the
 * evidence rather than just asserting a verdict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('check');
            $table->string('status');     // pass | warn | fail
            $table->string('severity');   // low | medium | high
            $table->string('title');
            $table->text('detail')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Account is a tenant - one client organisation. Every site, audit
 * and finding hangs off one, and a client user can only ever see their
 * own. This is deliberately in place from the first schema migration
 * rather than retrofitted: adding an owner column to tables that
 * already hold data means backfilling rows whose owner nobody
 * remembers, and every query written in the meantime has to be found
 * and re-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

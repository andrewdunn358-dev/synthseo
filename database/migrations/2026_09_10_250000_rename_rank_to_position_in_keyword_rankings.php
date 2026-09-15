<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rank` is a reserved word in MySQL 8 (it's a window function), so a
 * column literally named rank breaks any query that references it
 * without backtick-quoting - which Eloquent doesn't always do,
 * particularly in eager-load and aggregate queries. The failure is
 * silent from the outside: the insert throws, the controller's
 * redirect never completes, and the page just sits there looking like
 * nothing happened at all.
 *
 * Renamed to `position`, which says the same thing and isn't
 * reserved. Done as a rename rather than a drop-and-recreate so no
 * existing check history is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_rankings', function (Blueprint $table) {
            $table->renameColumn('rank', 'position');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_rankings', function (Blueprint $table) {
            $table->renameColumn('position', 'rank');
        });
    }
};

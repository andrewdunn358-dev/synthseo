<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->timestamps();

            // Every listing query filters by account, so the index
            // matches the access pattern rather than being on id alone.
            $table->index(['account_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};

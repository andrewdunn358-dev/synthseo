<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A minimal accountability trail for staff actions on OTHER accounts'
 * data - who edited or deleted what, and when. This exists for the
 * same reason the platform admin view itself needs care: the whole
 * point of the staff role is bypassing account isolation, and GDPR's
 * accountability principle (Article 5(2)) means that bypass should
 * leave a record, not just a bare capability. If a client ever asks
 * "who touched my account and when", this is the answer.
 *
 * staff_user_id nullable rather than a hard foreign key with
 * cascadeOnDelete - deleting the staff member who took an action
 * should never delete the record that they took it. nullOnDelete
 * keeps the log entry with an unattributed actor rather than losing
 * the entry itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};

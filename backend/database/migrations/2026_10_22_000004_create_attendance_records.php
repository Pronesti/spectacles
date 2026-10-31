<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unique(['id', 'event_id']);
            $table->timestamps();
        });
        Schema::create('attendance_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index();
            $table->foreignId('event_id')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('interested')->default(false);
            $table->boolean('tickets_bought')->default(false);
            $table->unique(['user_id', 'event_id']);
            $table->foreign(['attendance_id', 'event_id'], 'attendance_members_attendance_event_fk')
                ->references(['id', 'event_id'])->on('attendances')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('attendance_member_seats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_member_id')->index()->constrained()->cascadeOnDelete();
            $table->string('section_label')->nullable();
            $table->string('row_label')->nullable();
            $table->string('seat_label')->nullable();
            $table->text('label')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('attendance_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index()->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('label')->nullable();
            $table->string('type')->default('other');
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX attendance_invitations_pending_unique ON attendance_invitations (attendance_id, recipient_user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_links');
        Schema::dropIfExists('attendance_invitations');
        Schema::dropIfExists('attendance_member_seats');
        Schema::dropIfExists('attendance_members');
        Schema::dropIfExists('attendances');
    }
};

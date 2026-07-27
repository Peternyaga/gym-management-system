<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('class_series', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity');
            $table->string('recurrence_rule')->nullable();
            $table->time('starts_at_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('booking_opens_days')->default(14);
            $table->unsignedSmallInteger('cancellation_cutoff_minutes')->default(120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('class_instances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('series_id')->nullable()->constrained('class_series')->nullOnDelete();
            $table->foreignUlid('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at']);
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('class_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('member_id')->constrained('member_profiles')->cascadeOnDelete();
            $table->foreignUlid('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('booked')->index();
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
            $table->unique(['class_instance_id', 'member_id']);
        });

        Schema::create('kiosk_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('checkins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->constrained('member_profiles')->cascadeOnDelete();
            $table->foreignUlid('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('class_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('kiosk_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method')->default('staff');
            $table->string('result')->default('accepted')->index();
            $table->string('denial_reason')->nullable();
            $table->timestamp('checked_in_at')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'member_id', 'checked_in_at']);
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('member_id')->nullable()->constrained('member_profiles')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->text('body');
            $table->string('provider')->default('africastalking');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('status')->default('queued')->index();
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });

        Schema::create('data_subject_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('member_id')->constrained('member_profiles')->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('received')->index();
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduler_heartbeats', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->timestamp('last_ran_at');
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_heartbeats');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('kiosk_devices');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('class_instances');
        Schema::dropIfExists('class_series');
        Schema::dropIfExists('class_types');
    }
};

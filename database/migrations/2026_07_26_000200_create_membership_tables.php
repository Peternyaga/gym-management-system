<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_number');
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->boolean('adult_confirmed')->default(false);
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'member_number']);
            $table->unique(['tenant_id', 'phone']);
        });

        Schema::create('consent_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body');
            $table->boolean('required')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'type', 'version']);
        });

        Schema::create('member_consents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('member_id')->constrained('member_profiles')->cascadeOnDelete();
            $table->foreignUlid('consent_version_id')->constrained()->restrictOnDelete();
            $table->boolean('accepted');
            $table->string('method')->default('self_service');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'consent_version_id']);
        });

        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->index();
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('joining_fee_minor')->default(0);
            $table->string('billing_interval')->nullable();
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedInteger('pack_credits')->nullable();
            $table->string('credit_scope')->nullable();
            $table->unsignedSmallInteger('pack_expiry_days')->nullable();
            $table->unsignedSmallInteger('grace_days')->default(3);
            $table->json('access_rules')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->constrained('member_profiles')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->json('plan_snapshot');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('pause_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('credits_remaining')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'member_id', 'status']);
        });

        Schema::create('pack_credit_ledger', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('membership_id')->constrained()->cascadeOnDelete();
            $table->integer('change');
            $table->string('reason');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->integer('balance_after');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_credit_ledger');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('member_consents');
        Schema::dropIfExists('consent_versions');
        Schema::dropIfExists('member_profiles');
    }
};

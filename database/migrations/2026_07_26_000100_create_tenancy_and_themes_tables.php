<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->boolean('is_platform_admin')->default(false)->after('password');
        });

        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedInteger('member_limit')->default(1000);
            $table->unsignedInteger('staff_limit')->default(20);
            $table->unsignedInteger('sms_limit')->default(500);
            $table->unsignedInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('trialing')->index();
            $table->string('currency', 3)->default('KES');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->char('published_theme_id', 26)->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('county')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member')->index();
            $table->string('status')->default('active');
            $table->foreignUlid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('tenant_themes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('name')->default('Gym theme');
            $table->string('status')->default('draft')->index();
            $table->json('configuration');
            $table->json('validation_summary')->nullable();
            $table->foreignUlid('source_theme_id')->nullable()->constrained('tenant_themes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'version']);
        });

        Schema::create('theme_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('theme_id')->nullable()->constrained('tenant_themes')->nullOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('mime', 80);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64);
            $table->string('alt_text')->nullable();
            $table->decimal('focal_x', 5, 2)->default(50);
            $table->decimal('focal_y', 5, 2)->default(50);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('saas_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('trialing')->index();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('platform_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('theme_assets');
        Schema::dropIfExists('tenant_themes');
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('saas_plans');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'is_platform_admin']);
        });
    }
};

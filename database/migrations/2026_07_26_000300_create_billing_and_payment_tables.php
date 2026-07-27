<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('member_id')->nullable()->constrained('member_profiles')->nullOnDelete();
            $table->string('purpose')->default('member');
            $table->string('number');
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->default('KES');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_connections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->text('encrypted_configuration');
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'mode']);
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('mpesa');
            $table->string('status')->default('created')->index();
            $table->string('phone', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key');
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('response_code')->nullable();
            $table->text('response_description')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider');
            $table->string('event_key')->nullable();
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'payload_hash']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('status')->default('confirmed');
            $table->string('receipt_number')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('unapplied_minor')->default(0);
            $table->timestamp('paid_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('merchant_connections');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};

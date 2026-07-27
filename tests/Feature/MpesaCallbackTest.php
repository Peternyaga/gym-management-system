<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Payments\MpesaCallbackProcessor;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_callback_is_allocated_exactly_once(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Payments Gym');
        $location = $tenant->locations()->first();
        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'number' => 'INV-0001',
            'status' => 'issued',
            'currency' => 'KES',
            'subtotal_minor' => 250000,
            'total_minor' => 250000,
            'issued_at' => now(),
            'due_at' => now(),
        ]);
        PaymentIntent::query()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'status' => 'submitted',
            'phone' => '+254712345678',
            'amount_minor' => 250000,
            'idempotency_key' => 'checkout-one',
            'checkout_request_id' => 'ws_CO_123',
            'merchant_request_id' => 'merchant-123',
        ]);
        $payload = [
            'Body' => ['stkCallback' => [
                'MerchantRequestID' => 'merchant-123',
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'Processed',
                'CallbackMetadata' => ['Item' => [
                    ['Name' => 'Amount', 'Value' => 2500],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => 'TST1234567'],
                    ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                ]],
            ]],
        ];
        $processor = app(MpesaCallbackProcessor::class);

        $processor->process($payload);
        $processor->process($payload);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'paid_minor' => 250000]);
        $this->assertDatabaseHas('payment_intents', ['checkout_request_id' => 'ws_CO_123', 'status' => 'succeeded']);
    }
}

<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MpesaCallbackProcessor
{
    public function process(array $payload): WebhookEvent
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $event = WebhookEvent::query()->firstOrCreate(
            ['provider' => 'mpesa', 'payload_hash' => $hash],
            ['event_key' => data_get($payload, 'Body.stkCallback.CheckoutRequestID'), 'payload' => $payload, 'status' => 'received'],
        );

        if (! $event->wasRecentlyCreated || $event->processed_at !== null) {
            return $event;
        }

        return DB::transaction(function () use ($event, $payload): WebhookEvent {
            $callback = data_get($payload, 'Body.stkCallback', []);
            $intent = PaymentIntent::query()
                ->withoutGlobalScopes()
                ->where('checkout_request_id', $callback['CheckoutRequestID'] ?? null)
                ->lockForUpdate()
                ->first();

            if (! $intent) {
                $event->update(['status' => 'unmatched', 'processed_at' => now()]);

                return $event;
            }

            if ((int) ($callback['ResultCode'] ?? -1) !== 0) {
                if (! in_array($intent->status, ['succeeded', 'manually_confirmed'], true)) {
                    $intent->update(['status' => 'failed', 'response_description' => $callback['ResultDesc'] ?? 'M-Pesa declined', 'resolved_at' => now()]);
                }
                $event->update(['status' => 'processed', 'processed_at' => now()]);

                return $event;
            }

            $metadata = collect(data_get($callback, 'CallbackMetadata.Item', []))
                ->mapWithKeys(fn (array $item) => [$item['Name'] => $item['Value'] ?? null]);
            $receipt = (string) $metadata->get('MpesaReceiptNumber');
            $amountMinor = (int) round(((float) $metadata->get('Amount', 0)) * 100);
            $phone = '+'.ltrim((string) $metadata->get('PhoneNumber', ''), '+');

            if ($receipt === '' || $amountMinor <= 0 || $amountMinor !== (int) $intent->amount_minor) {
                $intent->update(['status' => 'pending_review', 'response_description' => 'Callback amount or receipt did not match the payment intent.']);
                $event->update(['status' => 'needs_review', 'processed_at' => now()]);

                return $event;
            }

            $payment = Payment::query()->withoutGlobalScopes()->firstOrCreate(
                ['receipt_number' => $receipt],
                [
                    'tenant_id' => $intent->tenant_id,
                    'payment_intent_id' => $intent->id,
                    'source' => 'mpesa',
                    'status' => 'confirmed',
                    'phone' => $phone,
                    'amount_minor' => $amountMinor,
                    'paid_at' => now(),
                    'metadata' => ['merchantRequestId' => $callback['MerchantRequestID'] ?? null],
                ],
            );

            if ($payment->wasRecentlyCreated) {
                $invoice = Invoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($intent->invoice_id);
                $needed = max(0, (int) $invoice->total_minor - (int) $invoice->paid_minor);
                $allocated = min($amountMinor, $needed);
                if ($allocated > 0) {
                    DB::table('payment_allocations')->insert([
                        'id' => (string) Str::ulid(),
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'amount_minor' => $allocated,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $invoice->update([
                    'paid_minor' => $invoice->paid_minor + $allocated,
                    'status' => $invoice->paid_minor + $allocated >= $invoice->total_minor ? 'paid' : 'partial',
                    'paid_at' => $invoice->paid_minor + $allocated >= $invoice->total_minor ? now() : null,
                ]);
                $payment->update(['unapplied_minor' => $amountMinor - $allocated]);
            }

            $intent->update(['status' => 'succeeded', 'resolved_at' => now()]);
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return $event;
        });
    }
}

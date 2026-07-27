<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\StkPushGateway;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PaymentController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('tenant/payments', [
            'invoices' => Invoice::query()->where('tenant_id', $tenant->id)->latest()->limit(100)->get(),
            'payments' => Payment::query()->where('tenant_id', $tenant->id)->latest('paid_at')->limit(100)->get(),
        ]);
    }

    public function showInvoice(Tenant $tenant, string $invoice): Response
    {
        $record = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($invoice)
            ->with('items')
            ->firstOrFail();

        $allocations = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.invoice_id', $record->id)
            ->where('payments.tenant_id', $tenant->id)
            ->get([
                'payments.id', 'payments.receipt_number', 'payments.source',
                'payments.status', 'payments.paid_at', 'payment_allocations.amount_minor',
            ]);

        return Inertia::render('tenant/payment-detail', [
            'kind' => 'invoice',
            'record' => $record,
            'member' => $record->member_id
                ? DB::table('member_profiles')->where('tenant_id', $tenant->id)->where('id', $record->member_id)
                    ->first(['id', 'name', 'member_number', 'phone'])
                : null,
            'allocations' => $allocations,
            'integrityWarning' => $record->paid_minor > 0 && $allocations->isEmpty()
                ? 'This invoice reports a paid amount but has no allocation ledger rows. Reconciliation review is required.'
                : null,
            'attempts' => PaymentIntent::query()->where('tenant_id', $tenant->id)
                ->where('invoice_id', $record->id)->latest()->get(),
        ]);
    }

    public function showPayment(Tenant $tenant, string $payment): Response
    {
        $record = Payment::query()->where('tenant_id', $tenant->id)->whereKey($payment)->firstOrFail();
        $allocations = DB::table('payment_allocations')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('payment_allocations.payment_id', $record->id)
            ->where('invoices.tenant_id', $tenant->id)
            ->get([
                'invoices.id', 'invoices.number', 'invoices.status',
                'invoices.total_minor', 'payment_allocations.amount_minor',
            ]);

        return Inertia::render('tenant/payment-detail', [
            'kind' => 'payment',
            'record' => $record,
            'member' => null,
            'allocations' => $allocations,
            'integrityWarning' => $record->unapplied_minor === 0 && $allocations->isEmpty()
                ? 'This payment reports no unapplied credit but has no invoice allocation rows. Reconciliation review is required.'
                : null,
            'attempts' => $record->payment_intent_id
                ? PaymentIntent::query()->where('tenant_id', $tenant->id)->whereKey($record->payment_intent_id)->get()
                : [],
        ]);
    }

    public function initiate(Request $request, Tenant $tenant, StkPushGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'string'],
            'phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
        ]);
        $invoice = Invoice::query()->where('tenant_id', $tenant->id)->whereKey($data['invoice_id'])->firstOrFail();
        abort_if($invoice->status === 'paid', 409, 'Invoice is already paid.');
        $key = $request->header('Idempotency-Key') ?: (string) str()->uuid();

        $intent = PaymentIntent::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'idempotency_key' => $key],
            [
                'invoice_id' => $invoice->id,
                'provider' => 'mpesa',
                'status' => 'pending_configuration',
                'phone' => str_starts_with($data['phone'], '0') ? '+254'.substr($data['phone'], 1) : $data['phone'],
                'amount_minor' => $invoice->total_minor - $invoice->paid_minor,
                'response_description' => 'M-Pesa credentials have not been connected for this environment.',
            ],
        );

        try {
            $result = $gateway->initiate([
                'phone' => $intent->phone,
                'amountMinor' => (int) $intent->amount_minor,
                'accountReference' => $invoice->number,
                'description' => "{$tenant->name} membership",
                'callbackUrl' => route('webhooks.mpesa', config('services.mpesa.deployment_token')),
                'idempotencyKey' => $key,
            ]);
            $intent->update([
                'status' => $result['accepted'] ? 'submitted' : 'rejected',
                'merchant_request_id' => $result['merchantRequestId'],
                'checkout_request_id' => $result['checkoutRequestId'],
                'response_code' => $result['responseCode'],
                'response_description' => $result['responseDescription'],
                'submitted_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Check the phone for the M-Pesa prompt.');
    }
}

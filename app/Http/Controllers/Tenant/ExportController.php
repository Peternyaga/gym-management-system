<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function etims(Tenant $tenant): StreamedResponse
    {
        return response()->streamDownload(function () use ($tenant): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }
            fputcsv($out, ['seller_name', 'buyer_name', 'invoice_number', 'issued_at', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'paid_minor', 'status']);
            Invoice::query()
                ->where('invoices.tenant_id', $tenant->id)
                ->leftJoin('member_profiles', 'member_profiles.id', '=', 'invoices.member_id')
                ->orderBy('invoices.issued_at')
                ->chunk(200, function ($rows) use ($out, $tenant): void {
                    foreach ($rows as $invoice) {
                        fputcsv($out, [
                            $tenant->name,
                            $invoice->name ?? '',
                            $invoice->number,
                            $invoice->issued_at,
                            $invoice->currency,
                            $invoice->subtotal_minor,
                            $invoice->tax_minor,
                            $invoice->total_minor,
                            $invoice->paid_minor,
                            $invoice->status,
                        ]);
                    }
                });
            fclose($out);
        }, "etims-preparation-{$tenant->slug}.csv", ['Content-Type' => 'text/csv']);
    }
}

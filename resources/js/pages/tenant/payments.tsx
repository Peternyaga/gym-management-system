import { Head, Link, usePage } from '@inertiajs/react';
import { Download, Smartphone } from 'lucide-react';
import { EmptyState, Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Invoice = {
    id: string;
    number: string;
    status: string;
    total_minor: number;
    paid_minor: number;
    issued_at?: string;
    due_at?: string;
};
type Payment = {
    id: string;
    receipt_number?: string;
    source: string;
    status: string;
    amount_minor: number;
    paid_at: string;
};

export default function Payments({
    invoices,
    payments,
}: {
    invoices: Invoice[];
    payments: Payment[];
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };

    return (
        <>
            <Head title="Payments" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Collections"
                    title="Money, reconciled"
                    description="Issued invoices, confirmed payments, and preparation exports for your records."
                    action={
                        tenant.role === 'owner' || tenant.role === 'manager' ? (
                            <Button variant="outline" asChild>
                                <a href={`/t/${tenant.slug}/exports/etims`}>
                                    <Download /> eTIMS-prep CSV
                                </a>
                            </Button>
                        ) : undefined
                    }
                />
                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Open invoices</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {invoices.length === 0 ? (
                                <EmptyState
                                    title="No invoices"
                                    body="Invoices appear here as members purchase plans."
                                />
                            ) : (
                                invoices.map((invoice) => (
                                    <Link
                                        key={invoice.id}
                                        href={`/t/${tenant.slug}/payments/invoices/${invoice.id}`}
                                        className="flex items-center justify-between rounded-lg border p-4"
                                    >
                                        <div>
                                            <p className="font-bold">
                                                {invoice.number}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Due{' '}
                                                {invoice.due_at
                                                    ? new Date(
                                                          invoice.due_at,
                                                      ).toLocaleDateString(
                                                          'en-KE',
                                                      )
                                                    : 'on issue'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-black">
                                                <Money
                                                    minor={
                                                        invoice.total_minor -
                                                        invoice.paid_minor
                                                    }
                                                />
                                            </p>
                                            <StatusPill
                                                value={invoice.status}
                                            />
                                        </div>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Confirmed payments</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {payments.length === 0 ? (
                                <div className="py-12 text-center">
                                    <Smartphone className="mx-auto size-8 text-muted-foreground" />
                                    <p className="mt-3 font-bold">
                                        Awaiting the first payment
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        M-Pesa callbacks and manual
                                        confirmations appear here.
                                    </p>
                                </div>
                            ) : (
                                payments.map((payment) => (
                                    <Link
                                        key={payment.id}
                                        href={`/t/${tenant.slug}/payments/${payment.id}`}
                                        className="flex items-center justify-between border-b pb-3"
                                    >
                                        <div>
                                            <p className="font-bold">
                                                {payment.receipt_number ??
                                                    payment.source}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(
                                                    payment.paid_at,
                                                ).toLocaleString('en-KE')}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-black">
                                                <Money
                                                    minor={payment.amount_minor}
                                                />
                                            </p>
                                            <StatusPill
                                                value={payment.status}
                                            />
                                        </div>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
                <p className="text-xs text-muted-foreground">
                    Receipts are labelled “Payment Receipt — Not an eTIMS Tax
                    Invoice.”
                </p>
            </main>
        </>
    );
}

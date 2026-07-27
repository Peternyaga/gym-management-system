import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type RecordData = {
    id: string;
    number?: string;
    receipt_number?: string;
    source?: string;
    status: string;
    currency?: string;
    subtotal_minor?: number;
    tax_minor?: number;
    total_minor?: number;
    paid_minor?: number;
    amount_minor?: number;
    unapplied_minor?: number;
    issued_at?: string;
    due_at?: string;
    paid_at?: string;
    phone?: string;
    items?: Array<{
        id: string;
        description: string;
        quantity: number;
        total_minor: number;
    }>;
};

export default function PaymentDetail({
    kind,
    record,
    member,
    allocations,
    attempts,
    integrityWarning,
}: {
    kind: 'invoice' | 'payment';
    record: RecordData;
    member?: { id: string; name: string; member_number: string; phone: string };
    allocations: Array<Record<string, string | number>>;
    attempts: Array<Record<string, string | number>>;
    integrityWarning?: string;
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; name: string };
    };
    const reference =
        kind === 'invoice'
            ? record.number
            : (record.receipt_number ?? record.source);

    return (
        <>
            <Head title={reference ?? 'Payment record'} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7 print:p-0">
                <Link
                    href={`/t/${tenant.slug}/payments`}
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground print:hidden"
                >
                    <ArrowLeft className="size-4" /> Payments
                </Link>
                <PageHeader
                    eyebrow={kind === 'invoice' ? 'Invoice' : 'Payment receipt'}
                    title={reference ?? 'Record'}
                    description={
                        kind === 'invoice'
                            ? 'Immutable issued invoice, allocations, and payment attempts.'
                            : 'Confirmed collection and its invoice allocations.'
                    }
                    action={
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer /> Print
                        </Button>
                    }
                />
                <div className="flex items-center gap-3">
                    <StatusPill value={record.status} />
                    <span className="text-sm text-muted-foreground">
                        {record.paid_at
                            ? new Date(record.paid_at).toLocaleString('en-KE')
                            : record.issued_at
                              ? new Date(record.issued_at).toLocaleString(
                                    'en-KE',
                                )
                              : ''}
                    </span>
                </div>
                {integrityWarning && (
                    <div
                        role="alert"
                        className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-950"
                    >
                        {integrityWarning}
                    </div>
                )}
                <section className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {kind === 'invoice'
                                    ? 'Invoice summary'
                                    : 'Payment summary'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {member && (
                                <Link
                                    href={`/t/${tenant.slug}/members/${member.id}`}
                                    className="block rounded-lg border p-4 hover:border-primary"
                                >
                                    <p className="font-black">{member.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {member.member_number} · {member.phone}
                                    </p>
                                </Link>
                            )}
                            <Row
                                label="Amount"
                                value={
                                    <Money
                                        minor={
                                            record.total_minor ??
                                            record.amount_minor ??
                                            0
                                        }
                                    />
                                }
                            />
                            {kind === 'invoice' && (
                                <>
                                    <Row
                                        label="Paid"
                                        value={
                                            <Money
                                                minor={record.paid_minor ?? 0}
                                            />
                                        }
                                    />
                                    <Row
                                        label="Outstanding"
                                        value={
                                            <Money
                                                minor={
                                                    (record.total_minor ?? 0) -
                                                    (record.paid_minor ?? 0)
                                                }
                                            />
                                        }
                                    />
                                </>
                            )}
                            {kind === 'payment' && (
                                <>
                                    <Row
                                        label="Source"
                                        value={record.source ?? '—'}
                                    />
                                    <Row
                                        label="Phone"
                                        value={record.phone ?? '—'}
                                    />
                                    <Row
                                        label="Unapplied credit"
                                        value={
                                            <Money
                                                minor={
                                                    record.unapplied_minor ?? 0
                                                }
                                            />
                                        }
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {kind === 'invoice'
                                    ? 'Line items'
                                    : 'Invoice allocations'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {kind === 'invoice'
                                ? record.items?.map((item) => (
                                      <div
                                          key={item.id}
                                          className="flex justify-between border-b pb-3"
                                      >
                                          <span>
                                              {item.quantity} ×{' '}
                                              {item.description}
                                          </span>
                                          <strong>
                                              <Money minor={item.total_minor} />
                                          </strong>
                                      </div>
                                  ))
                                : allocations.map((allocation) => (
                                      <Link
                                          key={String(allocation.id)}
                                          href={`/t/${tenant.slug}/payments/invoices/${allocation.id}`}
                                          className="flex justify-between border-b pb-3"
                                      >
                                          <span>
                                              {String(allocation.number)}
                                          </span>
                                          <strong>
                                              <Money
                                                  minor={Number(
                                                      allocation.amount_minor,
                                                  )}
                                              />
                                          </strong>
                                      </Link>
                                  ))}
                            {kind === 'invoice' && !record.items?.length && (
                                <p className="text-sm text-muted-foreground">
                                    No line items.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    {kind === 'invoice' && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Payments allocated</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {allocations.map((allocation) => (
                                    <Link
                                        key={String(allocation.id)}
                                        href={`/t/${tenant.slug}/payments/${allocation.id}`}
                                        className="flex justify-between border-b pb-3"
                                    >
                                        <span>
                                            {String(
                                                allocation.receipt_number ??
                                                    allocation.source,
                                            )}
                                        </span>
                                        <strong>
                                            <Money
                                                minor={Number(
                                                    allocation.amount_minor,
                                                )}
                                            />
                                        </strong>
                                    </Link>
                                ))}
                                {!allocations.length && (
                                    <p className="text-sm text-muted-foreground">
                                        No confirmed payment allocations.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}
                    <Card>
                        <CardHeader>
                            <CardTitle>M-Pesa attempts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {attempts.map((attempt) => (
                                <div
                                    key={String(attempt.id)}
                                    className="flex justify-between border-b pb-3 text-sm"
                                >
                                    <span>
                                        {String(attempt.phone ?? '')}
                                        <span className="block text-xs text-muted-foreground">
                                            {String(
                                                attempt.checkout_request_id ??
                                                    'No checkout identifier',
                                            )}
                                        </span>
                                    </span>
                                    <StatusPill
                                        value={String(attempt.status)}
                                    />
                                </div>
                            ))}
                            {!attempts.length && (
                                <p className="text-sm text-muted-foreground">
                                    No linked M-Pesa attempts.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>
                {kind === 'payment' && (
                    <p className="text-center text-xs text-muted-foreground">
                        Payment Receipt — Not an eTIMS Tax Invoice.
                    </p>
                )}
            </main>
        </>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between border-b pb-3">
            <span className="text-muted-foreground">{label}</span>
            <strong>{value}</strong>
        </div>
    );
}

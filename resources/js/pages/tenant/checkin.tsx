import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ScanLine, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Checkin = {
    id: string;
    name: string;
    member_number: string;
    result: string;
    denial_reason?: string;
    checked_in_at: string;
};

export default function CheckinPage({
    recent,
    metrics,
}: {
    recent: Checkin[];
    metrics: {
        acceptedToday: number;
        deniedToday: number;
        uniqueToday: number;
    };
}) {
    const { tenant, flash } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
        flash: {
            success?: string;
            error?: string;
            checkinResult?: {
                id: string;
                number: string;
                duplicate: boolean;
            };
        };
    };
    const form = useForm({ query: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/t/${tenant.slug}/check-in`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Check-in" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Front desk"
                    title="Fast, confident entry"
                    description="Scan a member QR or use their exact phone or member number."
                />
                <section className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Accepted today', metrics.acceptedToday],
                        ['Unique members', metrics.uniqueToday],
                        ['Denied today', metrics.deniedToday],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardContent className="pt-6">
                                <p className="text-3xl font-black">{value}</p>
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                {(flash.success || flash.error) && (
                    <div
                        className={`rounded-xl border p-6 text-xl font-black ${flash.error ? 'border-red-300 bg-red-50 text-red-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900'}`}
                    >
                        {flash.success ?? flash.error}
                        {flash.checkinResult ? (
                            <p className="mt-2 text-sm font-semibold">
                                {flash.checkinResult.number} ·{' '}
                                {flash.checkinResult.duplicate
                                    ? 'Original result reused'
                                    : 'New result recorded'}
                            </p>
                        ) : null}
                    </div>
                )}
                {tenant.role !== 'support' && (
                    <Card className="border-2 border-primary/20">
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <div className="relative flex-1">
                                    <Search className="absolute top-3.5 left-4 size-5 text-muted-foreground" />
                                    <Input
                                        autoFocus
                                        className="h-12 pl-12 text-base"
                                        placeholder="MEM-00001 or +254..."
                                        value={form.data.query}
                                        onChange={(e) =>
                                            form.setData(
                                                'query',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    className="h-12 px-7"
                                    disabled={form.processing}
                                >
                                    <ScanLine /> Check in
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent results</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {recent.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No check-ins recorded.
                            </p>
                        ) : (
                            recent.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/t/${tenant.slug}/check-in/${item.id}`}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="font-bold">{item.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.member_number} ·{' '}
                                            {new Date(
                                                item.checked_in_at,
                                            ).toLocaleTimeString('en-KE', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                        {item.denial_reason && (
                                            <p className="mt-1 text-xs text-red-700">
                                                {item.denial_reason}
                                            </p>
                                        )}
                                    </div>
                                    <StatusPill value={item.result} />
                                </Link>
                            ))
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Copy,
    CreditCard,
    QrCode,
    ShieldCheck,
} from 'lucide-react';
import { Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ClassItem = {
    id: string;
    name: string;
    starts_at: string;
    capacity: number;
    booked: number;
    booking?: { status: string };
};
type Props = {
    member: { name: string; member_number: string };
    membership?: {
        status: string;
        next_billing_at?: string;
        ends_at?: string;
        credits_remaining?: number;
    };
    qrToken: string;
    balanceMinor: number;
    invoices: Array<{
        id: string;
        number: string;
        status: string;
        total_minor: number;
        paid_minor: number;
        due_at?: string;
    }>;
    classes: ClassItem[];
    presentation: {
        layout: 'statusFirst' | 'community' | 'compact';
        content: {
            welcomeTitle: string;
            welcomeText: string;
            membershipLabel: string;
            balanceLabel: string;
            scheduleTitle: string;
            supportText: string;
        };
    };
};

export default function MemberHome({
    member,
    membership,
    qrToken,
    balanceMinor,
    invoices,
    classes,
    presentation,
}: Props) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; name: string };
    };

    return (
        <>
            <Head title="Member home" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow={member.member_number}
                    title={`${presentation.content.welcomeTitle}, ${member.name.split(' ')[0]}`}
                    description={presentation.content.welcomeText}
                />
                <div
                    className={`grid gap-4 md:grid-cols-3 ${
                        presentation.layout === 'compact' ? 'text-sm' : ''
                    }`}
                >
                    <Card>
                        <CardContent>
                            <ShieldCheck className="size-6 text-primary" />
                            <p className="mt-4 text-sm text-muted-foreground">
                                {presentation.content.membershipLabel}
                            </p>
                            <div className="mt-2">
                                <StatusPill
                                    value={membership?.status ?? 'pending'}
                                />
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                {membership?.next_billing_at
                                    ? `Next renewal ${new Date(membership.next_billing_at).toLocaleDateString('en-KE')}`
                                    : 'No renewal scheduled'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <CreditCard className="size-6 text-primary" />
                            <p className="mt-4 text-sm text-muted-foreground">
                                {presentation.content.balanceLabel}
                            </p>
                            <p className="mt-2 text-3xl font-black">
                                <Money minor={balanceMinor} />
                            </p>
                            <p className="mt-3 text-xs text-muted-foreground">
                                Pay from your open invoice with M-Pesa.
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-sidebar text-sidebar-foreground">
                        <CardContent>
                            <QrCode className="size-7 text-sidebar-primary" />
                            <p className="mt-3 font-black">
                                Rotating access code
                            </p>
                            <p className="mt-1 text-xs opacity-65">
                                Valid for approximately one minute.
                            </p>
                            <code className="mt-4 block truncate rounded bg-black/20 p-3 text-xs">
                                {qrToken}
                            </code>
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-3"
                                onClick={() =>
                                    navigator.clipboard.writeText(qrToken)
                                }
                            >
                                <Copy /> Copy for scanner
                            </Button>
                        </CardContent>
                    </Card>
                </div>
                <div className="grid gap-5 xl:grid-cols-[1.25fr_.75fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarDays className="size-5" />{' '}
                                {presentation.content.scheduleTitle}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {classes.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="font-bold">{item.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {new Date(
                                                item.starts_at,
                                            ).toLocaleString('en-KE', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}{' '}
                                            · {item.booked}/{item.capacity}
                                        </p>
                                    </div>
                                    {item.booking &&
                                    item.booking.status !== 'cancelled' ? (
                                        <div className="flex items-center gap-2">
                                            <StatusPill
                                                value={item.booking.status}
                                            />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.delete(
                                                        `/t/${tenant.slug}/member/bookings/${item.id}`,
                                                    )
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/t/${tenant.slug}/member/bookings/${item.id}`,
                                                )
                                            }
                                        >
                                            Book
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent invoices</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {invoices.map((invoice) => (
                                <div
                                    key={invoice.id}
                                    className="flex items-center justify-between border-b pb-3"
                                >
                                    <div>
                                        <p className="font-bold">
                                            {invoice.number}
                                        </p>
                                        <StatusPill value={invoice.status} />
                                    </div>
                                    <p className="font-black">
                                        <Money
                                            minor={
                                                invoice.total_minor -
                                                invoice.paid_minor
                                            }
                                        />
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
                <p className="text-center text-sm text-muted-foreground">
                    {presentation.content.supportText}
                </p>
            </main>
        </>
    );
}

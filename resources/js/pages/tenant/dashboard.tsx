import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CircleDollarSign,
    ScanLine,
    Users,
} from 'lucide-react';
import { Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    metrics: {
        members: number;
        activeMemberships: number;
        outstandingMinor: number;
        checkinsToday: number;
    };
    classes: Array<{
        id: string;
        name: string;
        startsAt: string;
        capacity: number;
        booked: number;
    }>;
    recentCheckins: Array<{
        id: string;
        name: string;
        result: string;
        checked_in_at: string;
    }>;
};

export default function Dashboard({ metrics, classes, recentCheckins }: Props) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; trialEndsAt?: string };
    };
    const cards = [
        {
            label: 'Total members',
            value: metrics.members,
            detail: `${metrics.activeMemberships} active memberships`,
            icon: Users,
        },
        {
            label: 'Outstanding',
            value: <Money minor={metrics.outstandingMinor} />,
            detail: 'Across open invoices',
            icon: CircleDollarSign,
        },
        {
            label: 'Check-ins today',
            value: metrics.checkinsToday,
            detail: 'Live front-desk count',
            icon: ScanLine,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <main className="flex flex-1 flex-col gap-7 p-4 md:p-7">
                <PageHeader
                    eyebrow="Today in Nairobi"
                    title="Gym at a glance"
                    description="Membership, collections, and attendance in one operational view."
                    action={
                        <Link
                            className="inline-flex min-h-11 items-center gap-2 rounded-md bg-primary px-4 text-sm font-bold text-primary-foreground"
                            href={`/g/${tenant.slug}`}
                        >
                            View public page <ArrowRight className="size-4" />
                        </Link>
                    }
                />
                <section className="grid gap-4 md:grid-cols-3">
                    {cards.map((item) => (
                        <Card key={item.label} className="overflow-hidden">
                            <CardContent className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.label}
                                    </p>
                                    <p className="mt-2 text-3xl font-black tracking-tight">
                                        {item.value}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {item.detail}
                                    </p>
                                </div>
                                <div className="rounded-lg bg-accent p-3 text-accent-foreground">
                                    <item.icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <section className="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Upcoming classes</CardTitle>
                            <CalendarDays className="size-5 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {classes.length === 0 && (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No upcoming classes yet.
                                </p>
                            )}
                            {classes.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="font-bold">{item.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {new Date(
                                                item.startsAt,
                                            ).toLocaleString('en-KE', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </p>
                                    </div>
                                    <p className="text-sm font-semibold">
                                        {item.booked}/{item.capacity}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent arrivals</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentCheckins.length === 0 && (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No check-ins today.
                                </p>
                            )}
                            {recentCheckins.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between border-b pb-3 last:border-0"
                                >
                                    <div>
                                        <p className="font-semibold">
                                            {item.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                item.checked_in_at,
                                            ).toLocaleTimeString('en-KE', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                    <StatusPill value={item.result} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </section>
            </main>
        </>
    );
}

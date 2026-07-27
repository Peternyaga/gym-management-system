import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Building2, Clock3, Users } from 'lucide-react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    metrics: {
        tenants: number;
        trialing: number;
        members: number;
        paymentIncidents: number;
    };
    tenants: Array<{
        id: string;
        name: string;
        slug: string;
        status: string;
        usersCount: number;
        membersCount: number;
        trial_ends_at?: string;
        lastSchedulerRun?: string;
    }>;
};

export default function PlatformDashboard({ metrics, tenants }: Props) {
    const cards = [
        { label: 'Gyms', value: metrics.tenants, icon: Building2 },
        { label: 'Trialing', value: metrics.trialing, icon: Clock3 },
        { label: 'Members managed', value: metrics.members, icon: Users },
        {
            label: 'Payment incidents',
            value: metrics.paymentIncidents,
            icon: AlertTriangle,
        },
    ];

    return (
        <>
            <Head title="Platform administration" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="GymFlow Kenya"
                    title="Platform control"
                    description="Tenant health, trials, usage, and operational incidents without exposing member records."
                />
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.label}>
                            <CardContent>
                                <card.icon className="size-5 text-primary" />
                                <p className="mt-4 text-sm text-muted-foreground">
                                    {card.label}
                                </p>
                                <p className="mt-1 text-3xl font-black">
                                    {card.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <div className="overflow-hidden rounded-xl border bg-card">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left text-xs tracking-wider text-muted-foreground uppercase">
                            <tr>
                                <th className="p-4">Gym</th>
                                <th className="p-4">Status</th>
                                <th className="p-4">Members</th>
                                <th className="hidden p-4 md:table-cell">
                                    Users
                                </th>
                                <th className="hidden p-4 lg:table-cell">
                                    Scheduler
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.map((tenant) => (
                                <tr
                                    key={tenant.id}
                                    className="border-t transition-colors hover:bg-muted/40"
                                >
                                    <td className="p-4">
                                        <Link
                                            href={`/platform/tenants/${tenant.slug}`}
                                            className="block rounded-sm font-bold outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            {tenant.name}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                /{tenant.slug} · Open gym
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="p-4">
                                        <StatusPill value={tenant.status} />
                                    </td>
                                    <td className="p-4 font-semibold">
                                        {tenant.membersCount}
                                    </td>
                                    <td className="hidden p-4 md:table-cell">
                                        {tenant.usersCount}
                                    </td>
                                    <td className="hidden p-4 text-xs text-muted-foreground lg:table-cell">
                                        {tenant.lastSchedulerRun
                                            ? new Date(
                                                  tenant.lastSchedulerRun,
                                              ).toLocaleString('en-KE')
                                            : 'Awaiting heartbeat'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}

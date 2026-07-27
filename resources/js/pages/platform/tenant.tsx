import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, ShieldAlert, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

type Gym = {
    id: string;
    name: string;
    slug: string;
    status: string;
    currency: string;
    trial_ends_at?: string;
    created_at: string;
    publicUrl: string;
    staffUrl: string;
    membersCount: number;
    staffCount: number;
    paymentIncidents: number;
    hasActiveSupportGrant: boolean;
    supportGrantExpiresAt?: string;
    activeSupportGrants: number;
    lastSchedulerRun?: string;
    locations: Array<{
        id: string;
        name: string;
        timezone: string;
        is_active: boolean;
    }>;
    staff: Array<{ id: string; name: string; email: string; role: string }>;
    themeVersions: Array<{
        id: string;
        version: number;
        name: string;
        status: string;
        published_at?: string;
    }>;
};

export default function PlatformTenant({
    gym,
    recentAudit,
}: {
    gym: Gym;
    recentAudit: Array<{ id: string; event: string; created_at: string }>;
}) {
    const statusForm = useForm({ status: gym.status });
    const updateStatus = (event: FormEvent) => {
        event.preventDefault();
        statusForm.patch(`/platform/tenants/${gym.slug}/status`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`${gym.name} · Platform`} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <Link
                    href="/platform"
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Tenant directory
                </Link>
                <PageHeader
                    eyebrow="Tenant management"
                    title={gym.name}
                    description={`/${gym.slug} · Created ${new Date(gym.created_at).toLocaleDateString('en-KE')}`}
                />
                <div className="flex flex-wrap items-center gap-3">
                    <StatusPill value={gym.status} />
                    <Button asChild variant="outline">
                        <a
                            href={gym.publicUrl}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Public page <ExternalLink className="size-4" />
                        </a>
                    </Button>
                    {gym.hasActiveSupportGrant ? (
                        <Button asChild variant="outline">
                            <a href={gym.staffUrl}>
                                Open read-only support view{' '}
                                <ExternalLink className="size-4" />
                            </a>
                        </Button>
                    ) : (
                        <Button variant="outline" disabled>
                            Owner approval required
                        </Button>
                    )}
                </div>
                <section className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <Users className="size-5 text-primary" />
                            <p className="mt-3 text-3xl font-black">
                                {gym.membersCount}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Members
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <Users className="size-5 text-primary" />
                            <p className="mt-3 text-3xl font-black">
                                {gym.staffCount}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Active staff
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <ShieldAlert className="size-5 text-primary" />
                            <p className="mt-3 text-3xl font-black">
                                {gym.paymentIncidents}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Payment incidents
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <section className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Access and lifecycle</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={updateStatus} className="space-y-3">
                                <Label htmlFor="tenant-status">
                                    Tenant status
                                </Label>
                                <div className="flex gap-2">
                                    <select
                                        id="tenant-status"
                                        className="h-10 flex-1 rounded-md border bg-background px-3"
                                        value={statusForm.data.status}
                                        onChange={(event) =>
                                            statusForm.setData(
                                                'status',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {[
                                            'trialing',
                                            'active',
                                            'restricted',
                                            'suspended',
                                            'cancelled',
                                        ].map((status) => (
                                            <option key={status}>
                                                {status}
                                            </option>
                                        ))}
                                    </select>
                                    <Button disabled={statusForm.processing}>
                                        Update status
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {gym.hasActiveSupportGrant
                                        ? `Read-only support access expires ${new Date(gym.supportGrantExpiresAt!).toLocaleString('en-KE')}.`
                                        : `Sensitive member records remain unavailable. Ask the gym owner to grant time-limited access from Support access. ${gym.activeSupportGrants} other active grant(s).`}
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Locations and staff</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {gym.locations.map((location) => (
                                <div
                                    key={location.id}
                                    className="flex justify-between border-b pb-3"
                                >
                                    <span className="font-semibold">
                                        {location.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {location.timezone}
                                    </span>
                                </div>
                            ))}
                            {gym.staff.map((person) => (
                                <div
                                    key={person.id}
                                    className="flex justify-between gap-3 text-sm"
                                >
                                    <span>
                                        {person.name}
                                        <span className="block text-xs text-muted-foreground">
                                            {person.email}
                                        </span>
                                    </span>
                                    <StatusPill value={person.role} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Theme history</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {gym.themeVersions.map((theme) => (
                                <div
                                    key={theme.id}
                                    className="flex items-center justify-between border-b pb-3"
                                >
                                    <span className="font-semibold">
                                        v{theme.version} · {theme.name}
                                    </span>
                                    <StatusPill value={theme.status} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent tenant audit</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentAudit.length ? (
                                recentAudit.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex justify-between gap-4 border-b pb-3 text-sm"
                                    >
                                        <span>{entry.event}</span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {new Date(
                                                entry.created_at,
                                            ).toLocaleString('en-KE')}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No tenant audit events yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </main>
        </>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function CheckinDetail({
    checkin,
    member,
    membership,
    location,
}: {
    checkin: {
        id: string;
        result: string;
        method: string;
        denial_reason?: string;
        checked_in_at: string;
    };
    member: {
        id: string;
        name: string;
        member_number: string;
        phone: string;
        status: string;
    };
    membership?: {
        id: string;
        status: string;
        starts_at: string;
        ends_at?: string;
        plan: { name: string };
    };
    location?: { name: string; timezone: string };
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string };
    };

    return (
        <>
            <Head title={`Check-in · ${member.name}`} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <Link
                    href={`/t/${tenant.slug}/check-in`}
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Check-in desk
                </Link>
                <PageHeader
                    eyebrow="Access decision"
                    title={member.name}
                    description={`${member.member_number} · ${new Date(checkin.checked_in_at).toLocaleString('en-KE')}`}
                />
                <div
                    className={`rounded-xl border p-6 ${checkin.result === 'accepted' ? 'border-emerald-300 bg-emerald-50 text-emerald-950' : 'border-red-300 bg-red-50 text-red-950'}`}
                >
                    <StatusPill value={checkin.result} />
                    <p className="mt-3 text-2xl font-black">
                        {checkin.result === 'accepted'
                            ? 'Entry accepted'
                            : 'Entry denied'}
                    </p>
                    {checkin.denial_reason && (
                        <p className="mt-1">{checkin.denial_reason}</p>
                    )}
                </div>
                <section className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Member</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Link
                                href={`/t/${tenant.slug}/members/${member.id}`}
                                className="font-black text-primary hover:underline"
                            >
                                {member.name}
                            </Link>
                            <p>{member.phone}</p>
                            <StatusPill value={member.status} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Decision context</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Row label="Method" value={checkin.method} />
                            <Row
                                label="Location"
                                value={location?.name ?? 'Unknown'}
                            />
                            <Row
                                label="Membership"
                                value={membership?.plan.name ?? 'None'}
                            />
                            <Row
                                label="Membership state"
                                value={membership?.status ?? 'No membership'}
                            />
                        </CardContent>
                    </Card>
                </section>
                <p className="text-xs text-muted-foreground">
                    Check-in decisions are immutable operational records.
                    Corrections should be documented through the audit process.
                </p>
            </main>
        </>
    );
}
function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between border-b pb-3">
            <span className="text-muted-foreground">{label}</span>
            <strong>{value}</strong>
        </div>
    );
}

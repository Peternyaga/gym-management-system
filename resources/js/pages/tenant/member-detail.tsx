import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Pause, Play, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Membership = {
    id: string;
    status: string;
    starts_at: string;
    ends_at?: string;
    pause_ends_at?: string;
    plan: { name: string };
};
type Member = {
    id: string;
    member_number: string;
    name: string;
    phone: string;
    email?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    status: string;
    joined_at?: string;
    memberships: Membership[];
};

export default function MemberDetail({
    member,
    invoices,
    bookings,
    checkins,
}: {
    member: Member;
    invoices: Array<{
        id: string;
        number: string;
        status: string;
        total_minor: number;
    }>;
    bookings: Array<{
        id: string;
        name: string;
        status: string;
        starts_at: string;
    }>;
    checkins: Array<{
        id: string;
        result: string;
        checked_in_at: string;
        denial_reason?: string;
    }>;
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const editable = ['owner', 'manager', 'receptionist'].includes(tenant.role);
    const form = useForm({
        name: member.name,
        phone: member.phone,
        email: member.email ?? '',
        emergency_contact_name: member.emergency_contact_name ?? '',
        emergency_contact_phone: member.emergency_contact_phone ?? '',
        status: member.status,
    });
    const save = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/t/${tenant.slug}/members/${member.id}`, {
            preserveScroll: true,
        });
    };
    const membershipAction = (
        membership: Membership,
        action: 'pause' | 'resume' | 'cancel',
    ) => {
        if (
            action === 'cancel' &&
            !window.confirm('Cancel this membership? This action is audited.')
        ) {
            return;
        }

        router.post(
            `/t/${tenant.slug}/members/${member.id}/membership-action`,
            {
                membership_id: membership.id,
                action,
                pause_days: 7,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={member.name} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <Link
                    href={`/t/${tenant.slug}/members`}
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Member directory
                </Link>
                <PageHeader
                    eyebrow={member.member_number}
                    title={member.name}
                    description="Contact, membership, billing, booking, and access history in one place."
                />
                <div className="flex items-center gap-3">
                    <StatusPill value={member.status} />
                </div>
                <section className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Member details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={save}
                                className="grid gap-4 sm:grid-cols-2"
                            >
                                <Field label="Full name">
                                    <Input
                                        disabled={!editable}
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Phone">
                                    <Input
                                        disabled={!editable}
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Email">
                                    <Input
                                        disabled={!editable}
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Member status">
                                    <select
                                        disabled={!editable}
                                        className="h-9 w-full rounded-md border bg-background px-3"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                        <option value="restricted">
                                            Restricted
                                        </option>
                                    </select>
                                </Field>
                                <Field label="Emergency contact">
                                    <Input
                                        disabled={!editable}
                                        value={form.data.emergency_contact_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'emergency_contact_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Emergency phone">
                                    <Input
                                        disabled={!editable}
                                        value={
                                            form.data.emergency_contact_phone
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'emergency_contact_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                {editable && (
                                    <div className="sm:col-span-2">
                                        <Button disabled={form.processing}>
                                            Save member
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Memberships</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {member.memberships.length ? (
                                member.memberships.map((membership) => (
                                    <div
                                        key={membership.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-black">
                                                    {membership.plan.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Started{' '}
                                                    {new Date(
                                                        membership.starts_at,
                                                    ).toLocaleDateString(
                                                        'en-KE',
                                                    )}
                                                </p>
                                            </div>
                                            <StatusPill
                                                value={membership.status}
                                            />
                                        </div>
                                        {(tenant.role === 'owner' ||
                                            tenant.role === 'manager') && (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                {membership.status ===
                                                'paused' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            membershipAction(
                                                                membership,
                                                                'resume',
                                                            )
                                                        }
                                                    >
                                                        <Play /> Resume
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            membershipAction(
                                                                membership,
                                                                'pause',
                                                            )
                                                        }
                                                    >
                                                        <Pause /> Pause 7 days
                                                    </Button>
                                                )}
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        membershipAction(
                                                            membership,
                                                            'cancel',
                                                        )
                                                    }
                                                >
                                                    <XCircle /> Cancel
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No membership has been issued.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoices</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {invoices.map((invoice) => (
                                <Link
                                    key={invoice.id}
                                    href={`/t/${tenant.slug}/payments/invoices/${invoice.id}`}
                                    className="flex justify-between border-b pb-3"
                                >
                                    <span className="font-semibold">
                                        {invoice.number}
                                    </span>
                                    <span>
                                        <Money minor={invoice.total_minor} /> ·{' '}
                                        {invoice.status}
                                    </span>
                                </Link>
                            ))}
                            {!invoices.length && (
                                <p className="text-sm text-muted-foreground">
                                    No invoices.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Bookings and check-ins</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {bookings.slice(0, 5).map((booking) => (
                                <div
                                    key={booking.id}
                                    className="flex justify-between border-b pb-3 text-sm"
                                >
                                    <span>{booking.name}</span>
                                    <StatusPill value={booking.status} />
                                </div>
                            ))}
                            {checkins.slice(0, 5).map((checkin) => (
                                <Link
                                    key={checkin.id}
                                    href={`/t/${tenant.slug}/check-in/${checkin.id}`}
                                    className="flex justify-between border-b pb-3 text-sm"
                                >
                                    <span>
                                        {new Date(
                                            checkin.checked_in_at,
                                        ).toLocaleString('en-KE')}
                                    </span>
                                    <StatusPill value={checkin.result} />
                                </Link>
                            ))}
                            {!bookings.length && !checkins.length && (
                                <p className="text-sm text-muted-foreground">
                                    No activity yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </main>
        </>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <Label className="block space-y-2">
            <span className="block">{label}</span>
            {children}
        </Label>
    );
}

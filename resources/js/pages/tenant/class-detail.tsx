import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarX, Check, UserX } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ClassItem = {
    id: string;
    name: string;
    starts_at: string;
    ends_at: string;
    capacity: number;
    status: string;
};
type Roster = {
    id: string;
    member_id: string;
    name: string;
    member_number: string;
    status: string;
    waitlist_position?: number;
};

export default function ClassDetail({
    classItem,
    roster,
}: {
    classItem: ClassItem;
    roster: Roster[];
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const editable = tenant.role === 'owner' || tenant.role === 'manager';
    const duration = Math.round(
        (new Date(classItem.ends_at).getTime() -
            new Date(classItem.starts_at).getTime()) /
            60000,
    );
    const localStart = new Date(classItem.starts_at);
    localStart.setMinutes(
        localStart.getMinutes() - localStart.getTimezoneOffset(),
    );
    const form = useForm({
        name: classItem.name,
        starts_at: localStart.toISOString().slice(0, 16),
        duration_minutes: duration.toString(),
        capacity: classItem.capacity.toString(),
    });
    const save = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/t/${tenant.slug}/schedule/${classItem.id}`, {
            preserveScroll: true,
        });
    };
    const cancel = () => {
        if (
            window.confirm('Cancel this class and release all active bookings?')
        ) {
            router.delete(`/t/${tenant.slug}/schedule/${classItem.id}`);
        }
    };
    const attendance = (booking: Roster, status: 'attended' | 'no_show') =>
        router.patch(
            `/t/${tenant.slug}/schedule/${classItem.id}/bookings/${booking.id}/attendance`,
            { status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={classItem.name} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <Link
                    href={`/t/${tenant.slug}/schedule`}
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Schedule
                </Link>
                <PageHeader
                    eyebrow="Class management"
                    title={classItem.name}
                    description={`${new Date(classItem.starts_at).toLocaleString('en-KE')} · ${roster.filter((item) => item.status === 'booked').length}/${classItem.capacity} booked`}
                />
                <StatusPill value={classItem.status} />
                <section className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Class settings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={save} className="space-y-4">
                                <Field label="Class name">
                                    <Input
                                        disabled={
                                            !editable ||
                                            classItem.status !== 'scheduled'
                                        }
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Starts">
                                    <Input
                                        disabled={
                                            !editable ||
                                            classItem.status !== 'scheduled'
                                        }
                                        type="datetime-local"
                                        value={form.data.starts_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Minutes">
                                        <Input
                                            disabled={
                                                !editable ||
                                                classItem.status !== 'scheduled'
                                            }
                                            type="number"
                                            value={form.data.duration_minutes}
                                            onChange={(e) =>
                                                form.setData(
                                                    'duration_minutes',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field label="Capacity">
                                        <Input
                                            disabled={
                                                !editable ||
                                                classItem.status !== 'scheduled'
                                            }
                                            type="number"
                                            value={form.data.capacity}
                                            onChange={(e) =>
                                                form.setData(
                                                    'capacity',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                                {editable &&
                                    classItem.status === 'scheduled' && (
                                        <div className="flex gap-3">
                                            <Button disabled={form.processing}>
                                                Save class
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                onClick={cancel}
                                            >
                                                <CalendarX /> Cancel class
                                            </Button>
                                        </div>
                                    )}
                            </form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Roster</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {roster.map((booking) => (
                                <div
                                    key={booking.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                                >
                                    <Link
                                        href={`/t/${tenant.slug}/members/${booking.member_id}`}
                                    >
                                        <p className="font-bold">
                                            {booking.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {booking.member_number}
                                            {booking.waitlist_position
                                                ? ` · Waitlist #${booking.waitlist_position}`
                                                : ''}
                                        </p>
                                    </Link>
                                    <div className="flex items-center gap-2">
                                        <StatusPill value={booking.status} />
                                        {booking.status === 'booked' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        attendance(
                                                            booking,
                                                            'attended',
                                                        )
                                                    }
                                                >
                                                    <Check /> Attended
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        attendance(
                                                            booking,
                                                            'no_show',
                                                        )
                                                    }
                                                >
                                                    <UserX /> No-show
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {!roster.length && (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No bookings for this class.
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

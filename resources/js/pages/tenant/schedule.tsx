import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CalendarPlus, Clock, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ClassItem = {
    id: string;
    name: string;
    starts_at: string;
    ends_at: string;
    capacity: number;
    booked: number;
    waitlisted: number;
    status: string;
};

export default function Schedule({ classes }: { classes: ClassItem[] }) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const [adding, setAdding] = useState(false);
    const form = useForm({
        name: '',
        starts_at: '',
        duration_minutes: '60',
        capacity: '20',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/t/${tenant.slug}/schedule`, {
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    return (
        <>
            <Head title="Schedule" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Classes & bookings"
                    title="Schedule the energy"
                    description="Publish upcoming sessions and keep capacity visible to the front desk."
                    action={
                        tenant.role === 'owner' || tenant.role === 'manager' ? (
                            <Button onClick={() => setAdding(!adding)}>
                                <CalendarPlus /> Add class
                            </Button>
                        ) : undefined
                    }
                />
                {adding && (
                    <Card>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <Field label="Class name">
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Starts">
                                    <Input
                                        type="datetime-local"
                                        value={form.data.starts_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Minutes">
                                    <Input
                                        type="number"
                                        min="15"
                                        value={form.data.duration_minutes}
                                        onChange={(e) =>
                                            form.setData(
                                                'duration_minutes',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Capacity">
                                    <Input
                                        type="number"
                                        min="1"
                                        value={form.data.capacity}
                                        onChange={(e) =>
                                            form.setData(
                                                'capacity',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                                <div className="flex gap-3 md:col-span-4">
                                    <Button disabled={form.processing}>
                                        Publish class
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setAdding(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
                {classes.length === 0 ? (
                    <EmptyState
                        title="The timetable is open"
                        body="Add the first class to make it bookable in the member experience."
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {classes.map((item) => (
                            <Card
                                key={item.id}
                                className="transition-colors hover:border-primary/60"
                            >
                                <CardContent className="flex items-center gap-5">
                                    <div className="min-w-16 rounded-lg bg-primary p-3 text-center text-primary-foreground">
                                        <p className="text-xs font-bold uppercase">
                                            {new Date(
                                                item.starts_at,
                                            ).toLocaleDateString('en-KE', {
                                                month: 'short',
                                            })}
                                        </p>
                                        <p className="text-2xl font-black">
                                            {new Date(item.starts_at).getDate()}
                                        </p>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <h2 className="truncate font-black">
                                                {item.name}
                                            </h2>
                                            <StatusPill value={item.status} />
                                        </div>
                                        <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                            <Clock className="size-4" />{' '}
                                            {new Date(
                                                item.starts_at,
                                            ).toLocaleTimeString('en-KE', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="flex items-center gap-1 font-bold">
                                            <Users className="size-4" />{' '}
                                            {item.booked}/{item.capacity}
                                        </p>
                                        {item.waitlisted > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                {item.waitlisted} waiting
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                                <Link
                                    href={`/t/${tenant.slug}/schedule/${item.id}`}
                                    className="mx-6 mb-5 inline-flex text-sm font-bold text-primary hover:underline"
                                >
                                    Manage class and roster →
                                </Link>
                            </Card>
                        ))}
                    </div>
                )}
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

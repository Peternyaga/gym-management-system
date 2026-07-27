import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState, Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Plan = {
    id: string;
    name: string;
    description?: string;
    type: string;
    price_minor: number;
    billing_interval?: string;
    duration_days?: number;
    pack_credits?: number;
    is_active: boolean;
};

export default function Plans({ plans }: { plans: Plan[] }) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const [adding, setAdding] = useState(false);
    const form = useForm({
        name: '',
        description: '',
        type: 'recurring',
        price: '',
        billing_interval: 'monthly',
        duration_days: '',
        pack_credits: '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/t/${tenant.slug}/plans`, {
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    return (
        <>
            <Head title="Membership plans" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Catalogue"
                    title="Membership plans"
                    description="Recurring, fixed-term, and class-pack offers with snapshotted pricing."
                    action={
                        tenant.role === 'owner' || tenant.role === 'manager' ? (
                            <Button onClick={() => setAdding(!adding)}>
                                <Plus /> New plan
                            </Button>
                        ) : undefined
                    }
                />
                {adding && (
                    <Card>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="grid gap-4 md:grid-cols-3"
                            >
                                <Field label="Plan name">
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Type">
                                    <select
                                        className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                        value={form.data.type}
                                        onChange={(e) =>
                                            form.setData('type', e.target.value)
                                        }
                                    >
                                        <option value="recurring">
                                            Recurring
                                        </option>
                                        <option value="fixed">
                                            Fixed term
                                        </option>
                                        <option value="pack">Class pack</option>
                                    </select>
                                </Field>
                                <Field label="Price (KES)">
                                    <Input
                                        type="number"
                                        min="0"
                                        value={form.data.price}
                                        onChange={(e) =>
                                            form.setData(
                                                'price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                                {form.data.type === 'recurring' && (
                                    <Field label="Billing interval">
                                        <select
                                            className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                            value={form.data.billing_interval}
                                            onChange={(e) =>
                                                form.setData(
                                                    'billing_interval',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="weekly">
                                                Weekly
                                            </option>
                                            <option value="monthly">
                                                Monthly
                                            </option>
                                            <option value="annual">
                                                Annual
                                            </option>
                                        </select>
                                    </Field>
                                )}
                                {form.data.type === 'fixed' && (
                                    <Field label="Duration (days)">
                                        <Input
                                            type="number"
                                            value={form.data.duration_days}
                                            onChange={(e) =>
                                                form.setData(
                                                    'duration_days',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                {form.data.type === 'pack' && (
                                    <Field label="Credits">
                                        <Input
                                            type="number"
                                            value={form.data.pack_credits}
                                            onChange={(e) =>
                                                form.setData(
                                                    'pack_credits',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                <Field label="Description">
                                    <Input
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <div className="flex items-end gap-3">
                                    <Button disabled={form.processing}>
                                        Save plan
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
                {plans.length === 0 ? (
                    <EmptyState
                        title="Build your membership menu"
                        body="Create a recurring plan, prepaid term, or credit pack."
                    />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {plans.map((plan) => (
                            <Card
                                key={plan.id}
                                className="transition-colors hover:border-primary/60"
                            >
                                <CardContent>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                {plan.type}
                                            </p>
                                            <h2 className="mt-2 text-xl font-black">
                                                {plan.name}
                                            </h2>
                                        </div>
                                        <StatusPill
                                            value={
                                                plan.is_active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                        />
                                    </div>
                                    <p className="mt-6 text-3xl font-black">
                                        <Money minor={plan.price_minor} />
                                    </p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {plan.billing_interval
                                            ? `Billed ${plan.billing_interval}`
                                            : plan.duration_days
                                              ? `${plan.duration_days} days`
                                              : `${plan.pack_credits} credits`}
                                    </p>
                                    <p className="mt-5 text-sm">
                                        {plan.description}
                                    </p>
                                    <Link
                                        href={`/t/${tenant.slug}/plans/${plan.id}`}
                                        className="mt-5 inline-flex text-sm font-bold text-primary hover:underline"
                                    >
                                        Manage plan →
                                    </Link>
                                </CardContent>
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

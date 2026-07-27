import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Archive } from 'lucide-react';
import type { FormEvent } from 'react';
import { Money, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    grace_days: number;
    is_public: boolean;
    is_active: boolean;
};

export default function PlanDetail({
    plan,
    usage,
}: {
    plan: Plan;
    usage: { total: number; active: number };
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const editable = tenant.role === 'owner' || tenant.role === 'manager';
    const form = useForm({
        name: plan.name,
        description: plan.description ?? '',
        type: plan.type,
        price: (plan.price_minor / 100).toString(),
        billing_interval: plan.billing_interval ?? 'monthly',
        duration_days: plan.duration_days?.toString() ?? '',
        pack_credits: plan.pack_credits?.toString() ?? '',
        grace_days: plan.grace_days.toString(),
        is_public: plan.is_public,
        is_active: plan.is_active,
    });
    const save = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/t/${tenant.slug}/plans/${plan.id}`, {
            preserveScroll: true,
        });
    };
    const remove = () => {
        if (
            window.confirm(
                'Remove this plan? Referenced plans will be safely archived.',
            )
        ) {
            router.delete(`/t/${tenant.slug}/plans/${plan.id}`);
        }
    };

    return (
        <>
            <Head title={plan.name} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <Link
                    href={`/t/${tenant.slug}/plans`}
                    className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Plans
                </Link>
                <PageHeader
                    eyebrow="Plan management"
                    title={plan.name}
                    description="Changes apply to future purchases; issued membership snapshots remain unchanged."
                />
                <section className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-3xl font-black">
                                <Money minor={plan.price_minor} />
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Current price
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-3xl font-black">
                                {usage.active}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Active memberships
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <StatusPill
                                value={plan.is_active ? 'active' : 'inactive'}
                            />
                            <p className="mt-3 text-sm text-muted-foreground">
                                {plan.is_public
                                    ? 'Shown publicly'
                                    : 'Hidden from signup'}
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Plan settings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={save}
                            className="grid gap-4 md:grid-cols-3"
                        >
                            <Field label="Name">
                                <Input
                                    disabled={!editable}
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Type">
                                <select
                                    disabled={!editable}
                                    className="h-9 w-full rounded-md border bg-background px-3"
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                >
                                    <option value="recurring">Recurring</option>
                                    <option value="fixed">Fixed term</option>
                                    <option value="pack">Pack</option>
                                </select>
                            </Field>
                            <Field label="Price (KES)">
                                <Input
                                    disabled={!editable}
                                    type="number"
                                    min="0"
                                    value={form.data.price}
                                    onChange={(e) =>
                                        form.setData('price', e.target.value)
                                    }
                                />
                            </Field>
                            {form.data.type === 'recurring' && (
                                <Field label="Billing interval">
                                    <select
                                        disabled={!editable}
                                        className="h-9 w-full rounded-md border bg-background px-3"
                                        value={form.data.billing_interval}
                                        onChange={(e) =>
                                            form.setData(
                                                'billing_interval',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </Field>
                            )}
                            {form.data.type === 'fixed' && (
                                <Field label="Duration days">
                                    <Input
                                        disabled={!editable}
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
                                        disabled={!editable}
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
                            <Field label="Grace days">
                                <Input
                                    disabled={!editable}
                                    type="number"
                                    min="0"
                                    max="30"
                                    value={form.data.grace_days}
                                    onChange={(e) =>
                                        form.setData(
                                            'grace_days',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Description">
                                <Input
                                    disabled={!editable}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="flex flex-col gap-2 pt-6 text-sm">
                                <label>
                                    <input
                                        disabled={!editable}
                                        type="checkbox"
                                        checked={form.data.is_public}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_public',
                                                e.target.checked,
                                            )
                                        }
                                    />{' '}
                                    Public signup option
                                </label>
                                <label>
                                    <input
                                        disabled={!editable}
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                    />{' '}
                                    Active plan
                                </label>
                            </div>
                            {editable && (
                                <div className="flex gap-3 md:col-span-3">
                                    <Button disabled={form.processing}>
                                        Save changes
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={remove}
                                    >
                                        <Archive /> Remove or archive
                                    </Button>
                                </div>
                            )}
                        </form>
                    </CardContent>
                </Card>
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

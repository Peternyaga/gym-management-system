import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState, PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Member = {
    id: string;
    member_number: string;
    name: string;
    phone: string;
    email?: string;
    status: string;
    joined_at?: string;
    memberships: Array<{ status: string }>;
};

export default function Members({ members }: { members: Member[] }) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string };
    };
    const [adding, setAdding] = useState(false);
    const form = useForm({
        name: '',
        phone: '',
        email: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        adult_confirmed: false,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/t/${tenant.slug}/members`, {
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    return (
        <>
            <Head title="Members" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Member directory"
                    title="People, not paperwork"
                    description="Search, enroll, and follow each member’s access status."
                    action={
                        tenant.role !== 'support' ? (
                            <Button onClick={() => setAdding(!adding)}>
                                <Plus /> Add member
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
                                <Field label="Full name">
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Kenyan phone">
                                    <Input
                                        placeholder="0712 345 678"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                                <Field label="Email (optional)">
                                    <Input
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
                                <Field label="Emergency contact">
                                    <Input
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
                                <label className="flex min-h-11 items-center gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.adult_confirmed}
                                        onChange={(e) =>
                                            form.setData(
                                                'adult_confirmed',
                                                e.target.checked,
                                            )
                                        }
                                    />{' '}
                                    Member confirms they are 18+
                                </label>
                                <div className="flex gap-3 md:col-span-3">
                                    <Button disabled={form.processing}>
                                        Create member
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setAdding(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                                {Object.keys(form.errors).length > 0 && (
                                    <p className="text-sm text-destructive md:col-span-3">
                                        {Object.values(form.errors)[0]}
                                    </p>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                )}
                <div className="relative max-w-md">
                    <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder="Search member number, name, or phone"
                    />
                </div>
                {members.length === 0 ? (
                    <EmptyState
                        title="No members yet"
                        body="Add the first member from the front desk or share your public signup page."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left text-xs tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="p-4">Member</th>
                                    <th className="hidden p-4 md:table-cell">
                                        Contact
                                    </th>
                                    <th className="p-4">Access</th>
                                    <th className="hidden p-4 lg:table-cell">
                                        Joined
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {members.map((member) => (
                                    <tr key={member.id} className="border-t">
                                        <td className="p-4">
                                            <Link
                                                href={`/t/${tenant.slug}/members/${member.id}`}
                                                className="font-bold hover:text-primary hover:underline"
                                            >
                                                {member.name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {member.member_number} · Open
                                                member
                                            </p>
                                        </td>
                                        <td className="hidden p-4 md:table-cell">
                                            {member.phone}
                                            <br />
                                            <span className="text-muted-foreground">
                                                {member.email}
                                            </span>
                                        </td>
                                        <td className="p-4">
                                            <StatusPill
                                                value={
                                                    member.memberships[0]
                                                        ?.status ??
                                                    'no membership'
                                                }
                                            />
                                        </td>
                                        <td className="hidden p-4 lg:table-cell">
                                            {member.joined_at
                                                ? new Date(
                                                      member.joined_at,
                                                  ).toLocaleDateString('en-KE')
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
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

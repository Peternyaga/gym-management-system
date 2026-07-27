import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Clock3, ShieldCheck, ShieldX } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Grant = {
    id: string;
    platform_name: string;
    platform_email: string;
    granted_by_name: string;
    reason: string;
    expires_at: string;
    revoked_at?: string;
    created_at: string;
};

export default function SupportAccess({
    platformAdmins,
    grants,
}: {
    platformAdmins: Array<{ id: number; name: string; email: string }>;
    grants: Grant[];
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string };
    };
    const form = useForm({
        platform_user_id: platformAdmins[0]?.id.toString() ?? '',
        reason: '',
        hours: '4',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/t/${tenant.slug}/settings/support-access`, {
            onSuccess: () => form.reset('reason'),
        });
    };
    const isActive = (grant: Grant) =>
        !grant.revoked_at && new Date(grant.expires_at) > new Date();

    return (
        <>
            <Head title="Support access" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Privacy control"
                    title="Support access"
                    description="Approve short, read-only support sessions. Every grant, view, expiry, and revocation is auditable."
                />
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5" /> Grant temporary
                            access
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-3"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="support-admin">
                                    Platform administrator
                                </Label>
                                <select
                                    id="support-admin"
                                    className="h-9 w-full rounded-md border bg-background px-3"
                                    value={form.data.platform_user_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'platform_user_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {platformAdmins.map((admin) => (
                                        <option key={admin.id} value={admin.id}>
                                            {admin.name} · {admin.email}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="support-hours">Duration</Label>
                                <select
                                    id="support-hours"
                                    className="h-9 w-full rounded-md border bg-background px-3"
                                    value={form.data.hours}
                                    onChange={(event) =>
                                        form.setData(
                                            'hours',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {[1, 2, 4, 8, 12, 24].map((hours) => (
                                        <option key={hours} value={hours}>
                                            {hours} hour{hours === 1 ? '' : 's'}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-2 md:col-span-3">
                                <Label htmlFor="support-reason">
                                    Reason and approved scope
                                </Label>
                                <Input
                                    id="support-reason"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Investigate invoice allocation issue reported by the owner"
                                    required
                                />
                            </div>
                            <Button
                                className="w-fit md:col-span-3"
                                disabled={
                                    form.processing ||
                                    platformAdmins.length === 0
                                }
                            >
                                Grant read-only access
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Grant history</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {grants.map((grant) => (
                            <div
                                key={grant.id}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div>
                                    <p className="font-black">
                                        {grant.platform_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {grant.platform_email} · Approved by{' '}
                                        {grant.granted_by_name}
                                    </p>
                                    <p className="mt-2 text-sm">
                                        {grant.reason}
                                    </p>
                                    <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock3 className="size-3" /> Expires{' '}
                                        {new Date(
                                            grant.expires_at,
                                        ).toLocaleString('en-KE')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <StatusPill
                                        value={
                                            isActive(grant)
                                                ? 'active'
                                                : grant.revoked_at
                                                  ? 'revoked'
                                                  : 'expired'
                                        }
                                    />
                                    {isActive(grant) && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                router.delete(
                                                    `/t/${tenant.slug}/settings/support-access/${grant.id}`,
                                                )
                                            }
                                        >
                                            <ShieldX /> Revoke
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {!grants.length && (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No support access has been granted.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

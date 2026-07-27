import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function PageHeader({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow?: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                {eyebrow && (
                    <p className="mb-2 text-xs font-bold tracking-[0.18em] text-primary uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="text-3xl font-black tracking-tight">{title}</h1>
                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

export function Money({
    minor,
    currency = 'KES',
}: {
    minor: number | string;
    currency?: string;
}) {
    return (
        <>
            {new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency,
                minimumFractionDigits: 0,
            }).format(Number(minor) / 100)}
        </>
    );
}

export function StatusPill({ value }: { value: string }) {
    const good = [
        'active',
        'paid',
        'accepted',
        'confirmed',
        'scheduled',
    ].includes(value);
    const warning = [
        'grace',
        'pending',
        'partial',
        'trialing',
        'waitlisted',
    ].includes(value);

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                good &&
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                warning &&
                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                !good && !warning && 'bg-muted text-muted-foreground',
            )}
        >
            {value.replace('_', ' ')}
        </span>
    );
}

export function EmptyState({
    title,
    body,
    href,
    action,
}: {
    title: string;
    body: string;
    href?: string;
    action?: string;
}) {
    return (
        <div className="rounded-xl border border-dashed p-10 text-center">
            <h3 className="font-bold">{title}</h3>
            <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                {body}
            </p>
            {href && action && (
                <Button asChild className="mt-5">
                    <Link href={href}>{action}</Link>
                </Button>
            )}
        </div>
    );
}

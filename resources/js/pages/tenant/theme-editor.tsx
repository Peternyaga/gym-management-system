import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    Eye,
    History,
    Monitor,
    Moon,
    Palette,
    Smartphone,
    Sun,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { PageHeader, StatusPill } from '@/components/gym-ui';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PaletteConfig = {
    brand: string;
    accent: string;
    background: string;
    surface: string;
    text: string;
    muted: string;
    border: string;
};
type ThemeConfig = {
    name: string;
    defaultMode: string;
    colors: { light: PaletteConfig; dark: PaletteConfig };
    typographyPreset: string;
    radiusPreset: string;
    density: string;
    navigationStyle: string;
    publicLayout: string;
    memberLayout: string;
    assets: Record<string, string | null>;
    publicContent: {
        tagline: string;
        heroTitle: string;
        heroText: string;
        callToActionLabel: string;
    };
    memberContent: {
        welcomeTitle: string;
        welcomeText: string;
        membershipLabel: string;
        balanceLabel: string;
        scheduleTitle: string;
        supportText: string;
    };
};
type ThemeVersion = {
    id: string;
    version: number;
    name: string;
    status: string;
    published_at?: string;
    validation_summary?: {
        valid: boolean;
        errors: string[];
        checks: Array<{
            mode: string;
            label: string;
            ratio: number;
            minimum: number;
            passed: boolean;
        }>;
    };
};
const defaultMemberContent: ThemeConfig['memberContent'] = {
    welcomeTitle: 'Welcome back',
    welcomeText:
        'Your membership, bookings, payments, and access code are all here.',
    membershipLabel: 'Membership',
    balanceLabel: 'Outstanding balance',
    scheduleTitle: 'Upcoming classes',
    supportText: 'Need help? Speak to the front desk.',
};

export default function ThemeEditor({
    draft,
    draftAssets,
    published,
    history,
    options,
}: {
    draft: ThemeVersion & { configuration: ThemeConfig };
    draftAssets: Array<{
        id: string;
        type: string;
        url: string;
        alt_text?: string;
        focal_x: number;
        focal_y: number;
        width: number;
        height: number;
    }>;
    published?: ThemeVersion;
    history: ThemeVersion[];
    options: Record<string, string[]>;
}) {
    const { tenant } = usePage().props as unknown as {
        tenant: { slug: string; role: string; name: string };
    };
    const form = useForm<{ configuration: ThemeConfig }>({
        configuration: draft.configuration,
    });
    const assetForm = useForm<{
        type: string;
        file: File | null;
        alt_text: string;
        focal_x: number;
        focal_y: number;
    }>({
        type: 'lightLogo',
        file: null,
        alt_text: '',
        focal_x: 50,
        focal_y: 50,
    });
    const [mode, setMode] = useState<'light' | 'dark'>('light');
    const [previewSurface, setPreviewSurface] = useState<
        'public' | 'member' | 'staff' | 'kiosk'
    >('public');
    const config = {
        ...form.data.configuration,
        memberContent: {
            ...defaultMemberContent,
            ...form.data.configuration.memberContent,
        },
    };
    const colors = config.colors[mode];
    const previewStyle = useMemo(
        () =>
            ({
                '--preview-bg': colors.background,
                '--preview-surface': colors.surface,
                '--preview-text': colors.text,
                '--preview-muted': colors.muted,
                '--preview-brand': colors.brand,
                '--preview-accent': colors.accent,
                '--preview-border': colors.border,
            }) as React.CSSProperties,
        [colors],
    );
    const patch = (path: string[], value: string) => {
        const next = structuredClone(form.data.configuration) as ThemeConfig;
        let cursor: Record<string, unknown> = next as unknown as Record<
            string,
            unknown
        >;
        path.slice(0, -1).forEach((part) => {
            cursor = cursor[part] as Record<string, unknown>;
        });
        cursor[path.at(-1)!] = value;
        form.setData('configuration', next);
    };
    const save = (e: FormEvent) => {
        e.preventDefault();
        form.patch(`/t/${tenant.slug}/settings/theme/draft`, {
            preserveScroll: true,
        });
    };
    const publish = () =>
        router.post(
            `/t/${tenant.slug}/settings/theme/publish`,
            {},
            { preserveScroll: true },
        );
    const upload = (event: FormEvent) => {
        event.preventDefault();
        assetForm.post(`/t/${tenant.slug}/settings/theme/assets`, {
            forceFormData: true,
            onSuccess: () => assetForm.reset('file', 'alt_text'),
        });
    };

    return (
        <>
            <Head title="Theme studio" />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-7">
                <PageHeader
                    eyebrow="Brand system"
                    title="Theme studio"
                    description="Draft freely, verify accessibility, and publish every tenant surface as one immutable version."
                    action={
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/t/${tenant.slug}/settings/theme/validate`,
                                    )
                                }
                            >
                                <Check /> Validate
                            </Button>
                            {tenant.role === 'owner' && (
                                <Button onClick={publish}>
                                    Publish v{draft.version}
                                </Button>
                            )}
                        </div>
                    }
                />
                <div className="grid gap-6 2xl:grid-cols-[minmax(400px,0.85fr)_minmax(520px,1.15fr)]">
                    <div className="space-y-5">
                        <form onSubmit={save} className="space-y-5">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Palette className="size-5" /> Core
                                        identity
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <Field label="Theme name">
                                        <Input
                                            value={config.name}
                                            onChange={(e) =>
                                                patch(['name'], e.target.value)
                                            }
                                        />
                                    </Field>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField
                                            label="Typography"
                                            value={config.typographyPreset}
                                            values={options.typography}
                                            onChange={(v) =>
                                                patch(['typographyPreset'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Corners"
                                            value={config.radiusPreset}
                                            values={options.radii}
                                            onChange={(v) =>
                                                patch(['radiusPreset'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Density"
                                            value={config.density}
                                            values={options.densities}
                                            onChange={(v) =>
                                                patch(['density'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Navigation"
                                            value={config.navigationStyle}
                                            values={options.navigation}
                                            onChange={(v) =>
                                                patch(['navigationStyle'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Public layout"
                                            value={config.publicLayout}
                                            values={options.layouts}
                                            onChange={(v) =>
                                                patch(['publicLayout'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Member dashboard"
                                            value={config.memberLayout}
                                            values={options.memberLayouts}
                                            onChange={(v) =>
                                                patch(['memberLayout'], v)
                                            }
                                        />
                                        <SelectField
                                            label="Default mode"
                                            value={config.defaultMode}
                                            values={options.modes}
                                            onChange={(v) =>
                                                patch(['defaultMode'], v)
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>
                                            Accessible palettes
                                        </CardTitle>
                                        <div className="rounded-lg bg-muted p-1">
                                            <Button
                                                size="sm"
                                                type="button"
                                                variant={
                                                    mode === 'light'
                                                        ? 'default'
                                                        : 'ghost'
                                                }
                                                onClick={() => setMode('light')}
                                            >
                                                <Sun />
                                            </Button>
                                            <Button
                                                size="sm"
                                                type="button"
                                                variant={
                                                    mode === 'dark'
                                                        ? 'default'
                                                        : 'ghost'
                                                }
                                                onClick={() => setMode('dark')}
                                            >
                                                <Moon />
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-4">
                                    {(
                                        Object.keys(colors) as Array<
                                            keyof PaletteConfig
                                        >
                                    ).map((key) => (
                                        <label key={key} className="space-y-2">
                                            <span className="text-sm font-medium capitalize">
                                                {key}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="color"
                                                    className="size-10 rounded border"
                                                    value={colors[key]}
                                                    onChange={(e) =>
                                                        patch(
                                                            [
                                                                'colors',
                                                                mode,
                                                                key,
                                                            ],
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                <Input
                                                    className="font-mono"
                                                    value={colors[key]}
                                                    onChange={(e) =>
                                                        patch(
                                                            [
                                                                'colors',
                                                                mode,
                                                                key,
                                                            ],
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </label>
                                    ))}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Public page content</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {(
                                        [
                                            'tagline',
                                            'heroTitle',
                                            'heroText',
                                            'callToActionLabel',
                                        ] as const
                                    ).map((key) => (
                                        <Field
                                            key={key}
                                            label={key.replace(
                                                /([A-Z])/g,
                                                ' $1',
                                            )}
                                        >
                                            <Input
                                                value={
                                                    config.publicContent[key]
                                                }
                                                onChange={(e) =>
                                                    patch(
                                                        ['publicContent', key],
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    ))}
                                    <Button disabled={form.processing}>
                                        Save draft
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Saving never changes the live gym
                                        experience.
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Member dashboard content
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {(
                                        [
                                            'welcomeTitle',
                                            'welcomeText',
                                            'membershipLabel',
                                            'balanceLabel',
                                            'scheduleTitle',
                                            'supportText',
                                        ] as const
                                    ).map((key) => (
                                        <Field
                                            key={key}
                                            label={key.replace(
                                                /([A-Z])/g,
                                                ' $1',
                                            )}
                                        >
                                            <Input
                                                value={
                                                    config.memberContent[key]
                                                }
                                                onChange={(e) =>
                                                    patch(
                                                        ['memberContent', key],
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    ))}
                                    <p className="text-xs text-muted-foreground">
                                        This copy appears only in the branded
                                        member portal. Required payment and
                                        legal wording remains
                                        platform-controlled.
                                    </p>
                                </CardContent>
                            </Card>
                        </form>
                        <Card>
                            <CardHeader>
                                <CardTitle>Logos & imagery</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={upload}
                                    className="grid gap-4 sm:grid-cols-2"
                                >
                                    <SelectField
                                        label="Surface"
                                        value={assetForm.data.type}
                                        values={[
                                            'lightLogo',
                                            'darkLogo',
                                            'appIcon',
                                            'favicon',
                                            'heroImage',
                                            'kioskBackground',
                                        ]}
                                        onChange={(value) =>
                                            assetForm.setData('type', value)
                                        }
                                    />
                                    <Field label="Image file">
                                        <Input
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            onChange={(event) =>
                                                assetForm.setData(
                                                    'file',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            required
                                        />
                                    </Field>
                                    <Field label="Alt text">
                                        <Input
                                            value={assetForm.data.alt_text}
                                            onChange={(event) =>
                                                assetForm.setData(
                                                    'alt_text',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Members training together"
                                        />
                                    </Field>
                                    <Field
                                        label={`Horizontal focal point (${assetForm.data.focal_x}%)`}
                                    >
                                        <Input
                                            type="range"
                                            min="0"
                                            max="100"
                                            value={assetForm.data.focal_x}
                                            onChange={(event) =>
                                                assetForm.setData(
                                                    'focal_x',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label={`Vertical focal point (${assetForm.data.focal_y}%)`}
                                    >
                                        <Input
                                            type="range"
                                            min="0"
                                            max="100"
                                            value={assetForm.data.focal_y}
                                            onChange={(event) =>
                                                assetForm.setData(
                                                    'focal_y',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </Field>
                                    <div className="flex items-end">
                                        <Button
                                            disabled={
                                                assetForm.processing ||
                                                !assetForm.data.file
                                            }
                                        >
                                            Process image
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground sm:col-span-2">
                                        PNG, JPEG, or WebP only. Images are
                                        decoded, metadata-stripped, and
                                        re-encoded as optimized WebP.
                                    </p>
                                </form>
                                {draftAssets.length > 0 && (
                                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                        {draftAssets.map((asset) => (
                                            <div
                                                key={asset.id}
                                                className="overflow-hidden rounded-lg border"
                                            >
                                                <img
                                                    src={asset.url}
                                                    alt={asset.alt_text ?? ''}
                                                    className="h-28 w-full object-cover"
                                                    style={{
                                                        objectPosition: `${asset.focal_x}% ${asset.focal_y}%`,
                                                    }}
                                                />
                                                <div className="flex items-center justify-between gap-3 p-3">
                                                    <span className="text-xs font-bold">
                                                        {asset.type} ·{' '}
                                                        {asset.width}×
                                                        {asset.height}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Remove ${asset.type}`}
                                                        onClick={() =>
                                                            router.delete(
                                                                `/t/${tenant.slug}/settings/theme/assets/${asset.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                    <div className="space-y-5">
                        <Card className="sticky top-5 overflow-hidden">
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <Eye className="size-5" /> Live preview
                                </CardTitle>
                                <div className="flex gap-1 text-muted-foreground">
                                    <Monitor className="size-4" />
                                    <Smartphone className="size-4" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="mb-4 flex flex-wrap gap-2">
                                    {(
                                        [
                                            'public',
                                            'member',
                                            'staff',
                                            'kiosk',
                                        ] as const
                                    ).map((surface) => (
                                        <Button
                                            key={surface}
                                            type="button"
                                            size="sm"
                                            variant={
                                                previewSurface === surface
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                setPreviewSurface(surface)
                                            }
                                            className="capitalize"
                                        >
                                            {surface}
                                        </Button>
                                    ))}
                                </div>
                                <div
                                    style={previewStyle}
                                    className="overflow-hidden rounded-xl border border-[var(--preview-border)] bg-[var(--preview-bg)] text-[var(--preview-text)] shadow-xl"
                                >
                                    <div className="flex items-center justify-between border-b border-[var(--preview-border)] bg-[var(--preview-surface)] px-5 py-4">
                                        <strong>{tenant.name}</strong>
                                        <div className="flex gap-4 text-xs text-[var(--preview-muted)]">
                                            <span>Plans</span>
                                            <span>Schedule</span>
                                        </div>
                                    </div>
                                    <div className="grid min-h-[430px] place-items-center p-8 text-center">
                                        {previewSurface === 'public' ? (
                                            <div className="max-w-lg">
                                                <span className="rounded-full bg-[var(--preview-accent)] px-3 py-1 text-xs font-black text-[#151A16]">
                                                    {
                                                        config.publicContent
                                                            .tagline
                                                    }
                                                </span>
                                                <h2 className="mt-5 text-4xl font-black tracking-tight">
                                                    {
                                                        config.publicContent
                                                            .heroTitle
                                                    }
                                                </h2>
                                                <p className="mt-4 text-[var(--preview-muted)]">
                                                    {
                                                        config.publicContent
                                                            .heroText
                                                    }
                                                </p>
                                                <button
                                                    type="button"
                                                    className="mt-7 min-h-11 rounded-lg bg-[var(--preview-brand)] px-5 font-bold"
                                                    style={{
                                                        color: bestText(
                                                            colors.brand,
                                                        ),
                                                    }}
                                                >
                                                    {
                                                        config.publicContent
                                                            .callToActionLabel
                                                    }
                                                </button>
                                            </div>
                                        ) : previewSurface === 'member' ? (
                                            <div className="w-full max-w-lg text-left">
                                                <p className="text-xs font-bold text-[var(--preview-muted)]">
                                                    MEM-00042
                                                </p>
                                                <h2 className="mt-2 text-3xl font-black">
                                                    {
                                                        config.memberContent
                                                            .welcomeTitle
                                                    }
                                                    , Amina
                                                </h2>
                                                <p className="mt-2 text-[var(--preview-muted)]">
                                                    {
                                                        config.memberContent
                                                            .welcomeText
                                                    }
                                                </p>
                                                <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                                    <div className="rounded-lg border border-[var(--preview-border)] bg-[var(--preview-surface)] p-4">
                                                        <p className="text-xs text-[var(--preview-muted)]">
                                                            {
                                                                config
                                                                    .memberContent
                                                                    .membershipLabel
                                                            }
                                                        </p>
                                                        <strong>Active</strong>
                                                    </div>
                                                    <div className="rounded-lg border border-[var(--preview-border)] bg-[var(--preview-surface)] p-4">
                                                        <p className="text-xs text-[var(--preview-muted)]">
                                                            {
                                                                config
                                                                    .memberContent
                                                                    .balanceLabel
                                                            }
                                                        </p>
                                                        <strong>KES 0</strong>
                                                    </div>
                                                </div>
                                                <p className="mt-5 text-sm text-[var(--preview-muted)]">
                                                    {
                                                        config.memberContent
                                                            .supportText
                                                    }
                                                </p>
                                            </div>
                                        ) : previewSurface === 'staff' ? (
                                            <div className="w-full max-w-lg text-left">
                                                <p className="text-xs font-bold text-[var(--preview-muted)]">
                                                    GYM OPERATIONS
                                                </p>
                                                <h2 className="mt-2 text-3xl font-black">
                                                    Today at a glance
                                                </h2>
                                                <div className="mt-6 grid grid-cols-3 gap-3">
                                                    {[
                                                        'Members',
                                                        'Revenue',
                                                        'Check-ins',
                                                    ].map((label, index) => (
                                                        <div
                                                            key={label}
                                                            className="rounded-lg border border-[var(--preview-border)] bg-[var(--preview-surface)] p-4"
                                                        >
                                                            <strong className="text-2xl">
                                                                {
                                                                    [
                                                                        842,
                                                                        '48K',
                                                                        57,
                                                                    ][index]
                                                                }
                                                            </strong>
                                                            <p className="text-xs text-[var(--preview-muted)]">
                                                                {label}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="w-full max-w-lg rounded-xl bg-[var(--preview-surface)] p-8">
                                                <p className="text-xs font-bold text-[var(--preview-muted)]">
                                                    CHECK-IN KIOSK
                                                </p>
                                                <h2 className="mt-3 text-4xl font-black">
                                                    Scan your member QR
                                                </h2>
                                                <div className="mx-auto mt-7 grid size-32 place-items-center rounded-xl border-4 border-[var(--preview-brand)] text-sm font-black">
                                                    QR
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {draft.validation_summary && (
                                    <div
                                        className={`mt-4 rounded-lg border p-4 text-sm ${draft.validation_summary.valid ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-amber-300 bg-amber-50 text-amber-950'}`}
                                    >
                                        <p className="font-bold">
                                            {draft.validation_summary.valid
                                                ? 'Ready to publish'
                                                : `${draft.validation_summary.errors.length} accessibility issue(s)`}
                                        </p>
                                        {draft.validation_summary.errors
                                            .slice(0, 3)
                                            .map((error) => (
                                                <p key={error} className="mt-1">
                                                    {error}
                                                </p>
                                            ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <History className="size-5" /> Version
                                    history
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {history.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-bold">
                                                v{item.version} · {item.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.published_at
                                                    ? new Date(
                                                          item.published_at,
                                                      ).toLocaleString('en-KE')
                                                    : 'Not published'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusPill
                                                value={
                                                    item.id === published?.id
                                                        ? 'live'
                                                        : item.status
                                                }
                                            />
                                            {tenant.role === 'owner' &&
                                                item.id !== published?.id && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `/t/${tenant.slug}/settings/theme/rollback/${item.version}`,
                                                            )
                                                        }
                                                    >
                                                        Restore
                                                    </Button>
                                                )}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
        <Label className="block space-y-2 capitalize">
            <span className="block">{label}</span>
            {children}
        </Label>
    );
}
function SelectField({
    label,
    value,
    values,
    onChange,
}: {
    label: string;
    value: string;
    values: string[];
    onChange: (value: string) => void;
}) {
    return (
        <Field label={label}>
            <select
                className="h-9 w-full rounded-md border bg-background px-3 text-sm capitalize"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            >
                {values.map((item) => (
                    <option key={item} value={item}>
                        {item.replace(/([A-Z])/g, ' $1')}
                    </option>
                ))}
            </select>
        </Field>
    );
}
function bestText(hex: string) {
    const rgb = [1, 3, 5].map((index) =>
        parseInt(hex.slice(index, index + 2), 16),
    );

    return (rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) / 1000 > 145
        ? '#151A16'
        : '#FFFFFF';
}

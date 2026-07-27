import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Check, MapPin } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Money } from '@/components/gym-ui';

type Plan = {
    id: string;
    name: string;
    description?: string;
    type: string;
    price_minor: number;
    billing_interval?: string;
};
type GymProps = {
    gym: { name: string; slug: string; currency: string };
    theme: {
        version?: number;
        configuration: {
            assets: { heroImage?: string | null; lightLogo?: string | null };
            publicContent: {
                tagline: string;
                heroTitle: string;
                heroText: string;
                callToActionLabel: string;
            };
        };
    };
    plans: Plan[];
    classes: Array<{ id: string; name: string; starts_at: string }>;
};

export default function Gym({ gym, theme, plans, classes }: GymProps) {
    const { publicContent: content, assets } = theme.configuration;
    const { flash } = usePage().props as unknown as {
        flash: { success?: string; error?: string };
    };
    const [otpSent, setOtpSent] = useState(false);
    const [otpMessage, setOtpMessage] = useState('');
    const form = useForm({
        verification_id: '',
        otp: '',
        plan_id: plans[0]?.id ?? '',
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        adult_confirmed: false,
        privacy_accepted: false,
        membership_terms_accepted: false,
        waiver_accepted: false,
        notifications_accepted: true,
    });
    const sendOtp = async () => {
        setOtpMessage('');
        const token =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';
        const response = await fetch(`/g/${gym.slug}/otp`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ phone: form.data.phone }),
        });
        const data = (await response.json()) as {
            verificationId?: string;
            message?: string;
            developmentCode?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !data.verificationId) {
            setOtpMessage(
                data.errors?.phone?.[0] ?? 'Could not send the code.',
            );

            return;
        }

        form.setData('verification_id', data.verificationId);
        setOtpSent(true);
        setOtpMessage(
            data.developmentCode
                ? `Development code: ${data.developmentCode}`
                : (data.message ?? 'Code sent.'),
        );
    };
    const join = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/g/${gym.slug}/join`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={gym.name} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-20 border-b bg-background/90 backdrop-blur">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
                        <Link
                            href={`/g/${gym.slug}`}
                            className="flex items-center gap-3 font-black"
                        >
                            {assets.lightLogo ? (
                                <img
                                    src={assets.lightLogo}
                                    alt={`${gym.name} logo`}
                                    className="h-9 w-24 object-contain object-left"
                                />
                            ) : (
                                <span className="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground">
                                    {gym.name.slice(0, 2).toUpperCase()}
                                </span>
                            )}
                            {gym.name}
                        </Link>
                        <nav className="hidden gap-7 text-sm font-semibold md:flex">
                            <a href="#plans">Plans</a>
                            <a href="#schedule">Schedule</a>
                        </nav>
                        <Link
                            href={`/t/${gym.slug}/login`}
                            className="rounded-md border px-4 py-2 text-sm font-bold"
                        >
                            Member login
                        </Link>
                    </div>
                </header>
                <main>
                    <section className="relative overflow-hidden">
                        <div className="absolute inset-0 [background-image:radial-gradient(var(--accent)_1px,transparent_1px)] [background-size:24px_24px] opacity-20" />
                        <div className="relative mx-auto grid min-h-[620px] max-w-7xl items-center gap-10 px-5 py-20 lg:grid-cols-[1.15fr_.85fr]">
                            <div>
                                <p className="mb-5 inline-flex rounded-full bg-accent px-4 py-2 text-xs font-black tracking-widest text-accent-foreground uppercase">
                                    {content.tagline}
                                </p>
                                <h1 className="max-w-3xl text-5xl leading-[.95] font-black tracking-[-.05em] md:text-7xl">
                                    {content.heroTitle}
                                </h1>
                                <p className="mt-7 max-w-xl text-lg leading-8 text-muted-foreground">
                                    {content.heroText}
                                </p>
                                <a
                                    href="#plans"
                                    className="mt-8 inline-flex min-h-12 items-center gap-2 rounded-md bg-primary px-6 font-black text-primary-foreground"
                                >
                                    {content.callToActionLabel}
                                    <ArrowRight className="size-5" />
                                </a>
                            </div>
                            <div className="rounded-[2rem] border bg-card p-5 shadow-2xl">
                                <div className="aspect-[4/5] rounded-[1.4rem] bg-sidebar p-8 text-sidebar-foreground">
                                    <div
                                        className="flex h-full flex-col justify-between rounded-xl bg-cover bg-center p-4"
                                        style={
                                            assets.heroImage
                                                ? {
                                                      backgroundImage: `linear-gradient(#151a1680,#151a16cc), url("${assets.heroImage}")`,
                                                  }
                                                : undefined
                                        }
                                    >
                                        <div>
                                            <span className="text-xs font-bold tracking-widest uppercase opacity-70">
                                                Built for your next rep
                                            </span>
                                            <p className="mt-3 text-4xl font-black">
                                                Stronger every visit.
                                            </p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Stat
                                                value="44px"
                                                label="easy touch targets"
                                            />
                                            <Stat
                                                value="24/7"
                                                label="member portal"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section id="plans" className="bg-card py-20">
                        <div className="mx-auto max-w-7xl px-5">
                            <p className="text-xs font-black tracking-widest text-primary uppercase">
                                Memberships
                            </p>
                            <h2 className="mt-3 text-4xl font-black">
                                Choose your pace.
                            </h2>
                            <div className="mt-10 grid gap-5 md:grid-cols-3">
                                {plans.map((plan) => (
                                    <article
                                        key={plan.id}
                                        className="rounded-2xl border bg-background p-7"
                                    >
                                        <p className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                            {plan.type}
                                        </p>
                                        <h3 className="mt-2 text-2xl font-black">
                                            {plan.name}
                                        </h3>
                                        <p className="mt-6 text-4xl font-black">
                                            <Money minor={plan.price_minor} />
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {plan.billing_interval
                                                ? `per ${plan.billing_interval.replace('ly', '')}`
                                                : 'one payment'}
                                        </p>
                                        <p className="mt-6 min-h-12 text-sm text-muted-foreground">
                                            {plan.description}
                                        </p>
                                        <a
                                            href="#join"
                                            onClick={() =>
                                                form.setData('plan_id', plan.id)
                                            }
                                            className="mt-6 flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-primary font-bold text-primary-foreground"
                                        >
                                            <Check className="size-4" /> Select
                                            plan
                                        </a>
                                    </article>
                                ))}
                            </div>
                            {plans.length === 0 && (
                                <p className="mt-10 rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                                    Memberships will be published soon.
                                </p>
                            )}
                        </div>
                    </section>
                    <section
                        id="join"
                        className="bg-sidebar py-20 text-sidebar-foreground"
                    >
                        <div className="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[.7fr_1.3fr]">
                            <div>
                                <p className="text-xs font-black tracking-widest text-sidebar-primary uppercase">
                                    Join securely
                                </p>
                                <h2 className="mt-3 text-4xl font-black">
                                    Start in under five minutes.
                                </h2>
                                <p className="mt-5 max-w-md text-sm leading-7 opacity-70">
                                    Verify your Kenyan phone, accept the current
                                    agreements, then pay the issued invoice by
                                    M-Pesa. Access activates only after server
                                    confirmation.
                                </p>
                            </div>
                            <form
                                onSubmit={join}
                                className="grid gap-4 rounded-2xl bg-background p-6 text-foreground md:grid-cols-2"
                            >
                                {flash.success && (
                                    <p className="rounded-lg bg-emerald-100 p-4 text-sm font-bold text-emerald-900 md:col-span-2">
                                        {flash.success}
                                    </p>
                                )}
                                <JoinField label="Membership">
                                    <select
                                        className="h-11 w-full rounded-md border bg-background px-3"
                                        value={form.data.plan_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'plan_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {plans.map((plan) => (
                                            <option
                                                key={plan.id}
                                                value={plan.id}
                                            >
                                                {plan.name}
                                            </option>
                                        ))}
                                    </select>
                                </JoinField>
                                <TextField
                                    label="Full name"
                                    value={form.data.name}
                                    onChange={(value) =>
                                        form.setData('name', value)
                                    }
                                />
                                <JoinField label="Kenyan phone">
                                    <div className="flex gap-2">
                                        <input
                                            className="h-11 min-w-0 flex-1 rounded-md border bg-background px-3"
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
                                        <button
                                            type="button"
                                            onClick={sendOtp}
                                            className="rounded-md border px-3 text-xs font-bold"
                                        >
                                            Send code
                                        </button>
                                    </div>
                                    {otpMessage && (
                                        <span className="text-xs text-muted-foreground">
                                            {otpMessage}
                                        </span>
                                    )}
                                </JoinField>
                                <TextField
                                    label="Verification code"
                                    value={form.data.otp}
                                    onChange={(value) =>
                                        form.setData('otp', value)
                                    }
                                    disabled={!otpSent}
                                />
                                <TextField
                                    label="Email (optional)"
                                    value={form.data.email}
                                    onChange={(value) =>
                                        form.setData('email', value)
                                    }
                                    type="email"
                                    required={false}
                                />
                                <TextField
                                    label="Emergency contact"
                                    value={form.data.emergency_contact_name}
                                    onChange={(value) =>
                                        form.setData(
                                            'emergency_contact_name',
                                            value,
                                        )
                                    }
                                />
                                <TextField
                                    label="Emergency phone"
                                    value={form.data.emergency_contact_phone}
                                    onChange={(value) =>
                                        form.setData(
                                            'emergency_contact_phone',
                                            value,
                                        )
                                    }
                                />
                                <TextField
                                    label="Account password"
                                    value={form.data.password}
                                    onChange={(value) =>
                                        form.setData('password', value)
                                    }
                                    type="password"
                                />
                                <TextField
                                    label="Confirm password"
                                    value={form.data.password_confirmation}
                                    onChange={(value) =>
                                        form.setData(
                                            'password_confirmation',
                                            value,
                                        )
                                    }
                                    type="password"
                                />
                                <div className="space-y-3 text-sm md:col-span-2">
                                    {(
                                        [
                                            [
                                                'adult_confirmed',
                                                'I confirm I am 18 or older.',
                                            ],
                                            [
                                                'privacy_accepted',
                                                'I accept the privacy notice.',
                                            ],
                                            [
                                                'membership_terms_accepted',
                                                'I accept the membership terms.',
                                            ],
                                            [
                                                'waiver_accepted',
                                                'I accept the current waiver.',
                                            ],
                                            [
                                                'notifications_accepted',
                                                'Send me useful service notifications.',
                                            ],
                                        ] as const
                                    ).map(([key, label]) => (
                                        <label key={key} className="flex gap-3">
                                            <input
                                                type="checkbox"
                                                checked={form.data[key]}
                                                onChange={(e) =>
                                                    form.setData(
                                                        key,
                                                        e.target.checked,
                                                    )
                                                }
                                                required={
                                                    key !==
                                                    'notifications_accepted'
                                                }
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                                {Object.keys(form.errors).length > 0 && (
                                    <p className="text-sm text-red-700 md:col-span-2">
                                        {Object.values(form.errors)[0]}
                                    </p>
                                )}
                                <button
                                    disabled={form.processing || !otpSent}
                                    className="min-h-12 rounded-md bg-primary px-6 font-black text-primary-foreground disabled:opacity-50 md:col-span-2"
                                >
                                    Create account & invoice
                                </button>
                            </form>
                        </div>
                    </section>
                    <section id="schedule" className="py-20">
                        <div className="mx-auto max-w-7xl px-5">
                            <div className="flex items-end justify-between">
                                <div>
                                    <p className="text-xs font-black tracking-widest text-primary uppercase">
                                        Schedule
                                    </p>
                                    <h2 className="mt-3 text-4xl font-black">
                                        Next sessions.
                                    </h2>
                                </div>
                                <CalendarDays className="size-10 text-muted-foreground" />
                            </div>
                            <div className="mt-9 divide-y rounded-xl border bg-card">
                                {classes.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between p-5"
                                    >
                                        <div>
                                            <p className="font-black">
                                                {item.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                <MapPin className="mr-1 inline size-3" />{' '}
                                                Main gym
                                            </p>
                                        </div>
                                        <p className="text-right text-sm font-bold">
                                            {new Date(
                                                item.starts_at,
                                            ).toLocaleDateString('en-KE', {
                                                weekday: 'short',
                                                month: 'short',
                                                day: 'numeric',
                                            })}
                                            <br />
                                            <span className="text-muted-foreground">
                                                {new Date(
                                                    item.starts_at,
                                                ).toLocaleTimeString('en-KE', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </main>
                <footer className="border-t bg-sidebar py-10 text-sidebar-foreground">
                    <div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 sm:flex-row sm:justify-between">
                        <p className="font-black">{gym.name}</p>
                        <p className="text-sm opacity-70">
                            Powered by GymFlow Kenya · Privacy · Terms
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Stat({ value, label }: { value: string; label: string }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
            <p className="text-2xl font-black">{value}</p>
            <p className="text-xs opacity-60">{label}</p>
        </div>
    );
}
function JoinField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="space-y-2 text-sm font-semibold">
            <span>{label}</span>
            {children}
        </label>
    );
}
function TextField({
    label,
    value,
    onChange,
    type = 'text',
    disabled = false,
    required = true,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    disabled?: boolean;
    required?: boolean;
}) {
    return (
        <JoinField label={label}>
            <input
                type={type}
                disabled={disabled}
                required={required}
                className="h-11 w-full rounded-md border bg-background px-3 disabled:opacity-50"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </JoinField>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Palette,
    ScanLine,
    Smartphone,
} from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage().props;
    const features = [
        {
            icon: Smartphone,
            title: 'Kenyan payments',
            body: 'M-Pesa STK intents, durable callbacks, allocations, and reconciliation built into the ledger.',
        },
        {
            icon: Palette,
            title: 'Your gym, everywhere',
            body: 'One accessible theme powers public pages, login, staff, members, kiosks, receipts, and PWA identity.',
        },
        {
            icon: ScanLine,
            title: 'Fast front desk',
            body: 'Membership-aware check-ins, rotating access tokens, duplicate handling, and live class capacity.',
        },
    ];

    return (
        <>
            <Head title="GymFlow Kenya" />
            <div className="min-h-screen bg-[#F6F8F4] text-[#151A16]">
                <header className="border-b border-[#DCE2DA]">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-5">
                        <div className="flex items-center gap-3 font-black">
                            <span className="grid size-10 place-items-center rounded-lg bg-[#151A16] text-[#B5EE45]">
                                GF
                            </span>
                            GymFlow Kenya
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href="/dashboard"
                                    className="rounded-md bg-[#151A16] px-4 py-2 text-sm font-bold text-white"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="px-3 py-2 text-sm font-bold"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="rounded-md bg-[#151A16] px-4 py-2 text-sm font-bold text-white"
                                    >
                                        Start free
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>
                <main>
                    <section className="relative overflow-hidden">
                        <div className="absolute inset-0 [background-image:linear-gradient(#dce2da_1px,transparent_1px),linear-gradient(90deg,#dce2da_1px,transparent_1px)] [background-size:40px_40px] opacity-25" />
                        <div className="relative mx-auto grid min-h-[680px] max-w-7xl items-center gap-12 px-5 py-20 lg:grid-cols-[1.1fr_.9fr]">
                            <div>
                                <p className="inline-flex rounded-full bg-[#B5EE45] px-4 py-2 text-xs font-black tracking-widest uppercase">
                                    Built in Kenya, for growing gyms
                                </p>
                                <h1 className="mt-6 max-w-4xl text-6xl leading-[.92] font-black tracking-[-.06em] md:text-8xl">
                                    Run the gym. Build the brand.
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-[#657067]">
                                    Memberships, collections, classes, and
                                    access in one responsive system—fully themed
                                    for every gym you operate.
                                </p>
                                <div className="mt-9 flex flex-wrap gap-3">
                                    <Link
                                        href="/register"
                                        className="inline-flex min-h-12 items-center gap-2 rounded-md bg-[#151A16] px-6 font-black text-white"
                                    >
                                        Start a 14-day trial{' '}
                                        <ArrowRight className="size-5" />
                                    </Link>
                                    <a
                                        href="#features"
                                        className="inline-flex min-h-12 items-center rounded-md border border-[#151A16] px-6 font-bold"
                                    >
                                        Explore the system
                                    </a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-5 text-sm font-semibold text-[#657067]">
                                    {[
                                        'No card required',
                                        'KES & Nairobi defaults',
                                        'Installable PWA',
                                    ].map((item) => (
                                        <span
                                            key={item}
                                            className="flex items-center gap-2"
                                        >
                                            <CheckCircle2 className="size-4 text-[#238636]" />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <div className="rounded-[2rem] bg-[#151A16] p-5 text-white shadow-2xl">
                                <div className="rounded-[1.4rem] border border-white/10 bg-[#202821] p-6">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-xs font-bold tracking-widest text-[#B5EE45] uppercase">
                                                Pamoja Strength Club
                                            </p>
                                            <p className="mt-2 text-2xl font-black">
                                                Gym at a glance
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-[#B5EE45] px-3 py-1 text-xs font-black text-[#151A16]">
                                            Live
                                        </span>
                                    </div>
                                    <div className="mt-7 grid grid-cols-2 gap-3">
                                        <Metric value="1,248" label="Members" />
                                        <Metric
                                            value="KES 842k"
                                            label="Collected"
                                        />
                                        <Metric
                                            value="94"
                                            label="Today’s visits"
                                        />
                                        <Metric value="8" label="Classes" />
                                    </div>
                                    <div className="mt-4 rounded-xl bg-[#B5EE45] p-5 text-[#151A16]">
                                        <p className="text-xs font-bold uppercase">
                                            Next class
                                        </p>
                                        <p className="mt-1 text-xl font-black">
                                            Strength Circuit
                                        </p>
                                        <p className="mt-2 text-sm">
                                            18:00 · 14 of 18 booked
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section id="features" className="bg-white py-24">
                        <div className="mx-auto max-w-7xl px-5">
                            <p className="text-xs font-black tracking-widest uppercase">
                                One operating system
                            </p>
                            <h2 className="mt-3 max-w-2xl text-4xl font-black tracking-tight md:text-5xl">
                                Commercial discipline without losing your
                                identity.
                            </h2>
                            <div className="mt-12 grid gap-5 md:grid-cols-3">
                                {features.map((item) => (
                                    <article
                                        key={item.title}
                                        className="rounded-2xl border border-[#DCE2DA] bg-[#F6F8F4] p-7"
                                    >
                                        <span className="grid size-12 place-items-center rounded-xl bg-[#B5EE45]">
                                            <item.icon className="size-6" />
                                        </span>
                                        <h3 className="mt-6 text-xl font-black">
                                            {item.title}
                                        </h3>
                                        <p className="mt-3 text-sm leading-7 text-[#657067]">
                                            {item.body}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>
                </main>
                <footer className="bg-[#151A16] py-10 text-white">
                    <div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 sm:flex-row sm:justify-between">
                        <p className="font-black">GymFlow Kenya</p>
                        <p className="text-sm text-white/60">
                            Built for responsible, accessible gym operations.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
function Metric({ value, label }: { value: string; label: string }) {
    return (
        <div className="rounded-xl border border-white/10 bg-white/5 p-4">
            <p className="text-2xl font-black">{value}</p>
            <p className="mt-1 text-xs text-white/55">{label}</p>
        </div>
    );
}

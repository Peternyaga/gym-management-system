import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CreditCard,
    LayoutGrid,
    Palette,
    ScanLine,
    ShieldCheck,
    Users,
    WalletCards,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, tenant, tenants } = usePage().props as unknown as {
        auth: { user: { is_platform_admin?: boolean } };
        tenant?: { slug: string; role: string; name: string };
        tenants: Array<{ slug: string; role: string; name: string }>;
    };
    const isPlatform = Boolean(auth.user.is_platform_admin) && !tenant;
    const base = tenant ? `/t/${tenant.slug}` : '';
    const mainNavItems: NavItem[] = tenant
        ? tenant.role === 'member'
            ? [
                  {
                      title: 'Member home',
                      href: `${base}/member`,
                      icon: LayoutGrid,
                  },
              ]
            : [
                  {
                      title: 'Dashboard',
                      href: `${base}/dashboard`,
                      icon: LayoutGrid,
                  },
                  { title: 'Members', href: `${base}/members`, icon: Users },
                  { title: 'Plans', href: `${base}/plans`, icon: WalletCards },
                  {
                      title: 'Payments',
                      href: `${base}/payments`,
                      icon: CreditCard,
                  },
                  {
                      title: 'Schedule',
                      href: `${base}/schedule`,
                      icon: CalendarDays,
                  },
                  {
                      title: 'Check-in',
                      href: `${base}/check-in`,
                      icon: ScanLine,
                  },
                  ...(tenant.role === 'owner' || tenant.role === 'manager'
                      ? [
                            {
                                title: 'Theme studio',
                                href: `${base}/settings/theme`,
                                icon: Palette,
                            },
                        ]
                      : []),
                  ...(tenant.role === 'owner'
                      ? [
                            {
                                title: 'Support access',
                                href: `${base}/settings/support-access`,
                                icon: ShieldCheck,
                            },
                        ]
                      : []),
              ]
        : isPlatform
          ? [
                {
                    title: 'Tenant directory',
                    href: '/platform',
                    icon: Building2,
                },
            ]
          : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    tenant
                                        ? tenant.role === 'member'
                                            ? `${base}/member`
                                            : `${base}/dashboard`
                                        : '/dashboard'
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {tenant && tenants.length > 1 ? (
                    <label className="px-2 text-xs text-muted-foreground">
                        Switch gym
                        <select
                            className="mt-1 h-9 w-full rounded-md border bg-background px-2 text-sm text-foreground"
                            value={tenant.slug}
                            onChange={(event) => {
                                const next = tenants.find(
                                    (item) => item.slug === event.target.value,
                                );

                                if (next) {
                                    router.visit(
                                        `/t/${next.slug}/${next.role === 'member' ? 'member' : 'dashboard'}`,
                                    );
                                }
                            }}
                        >
                            {tenants.map((item) => (
                                <option key={item.slug} value={item.slug}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : null}
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={mainNavItems}
                    label={
                        isPlatform
                            ? 'Platform administration'
                            : tenant?.role === 'member'
                              ? 'Member portal'
                              : 'Gym operations'
                    }
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

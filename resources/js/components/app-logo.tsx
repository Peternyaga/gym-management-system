import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, tenant } = usePage().props as unknown as {
        name: string;
        tenant?: {
            status: string;
            themeAssets?: { lightLogo?: string; darkLogo?: string };
        };
    };

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {tenant?.themeAssets?.lightLogo ? (
                    <img
                        src={tenant.themeAssets.lightLogo}
                        alt=""
                        className="size-7 object-contain"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
                {tenant && (
                    <span className="truncate text-[10px] tracking-wider uppercase opacity-65">
                        {tenant.status}
                    </span>
                )}
            </div>
        </>
    );
}

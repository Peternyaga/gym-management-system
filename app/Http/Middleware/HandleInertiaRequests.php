<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $route = $request->route();
        $routeTenant = $route?->parameter('tenant');
        $isTenantSurface = str_starts_with((string) $route?->uri(), 't/{tenant}');
        $tenant = $isTenantSurface && $routeTenant instanceof Tenant ? $routeTenant : null;
        $user = $request->user();
        $publishedTheme = $tenant?->publishedTheme();
        $tenantRole = $tenant && $user
            ? DB::table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->value('role')
            : null;
        if ($tenant && $user?->is_platform_admin && $tenantRole === null) {
            $hasSupportGrant = DB::table('support_access_grants')
                ->where('tenant_id', $tenant->id)
                ->where('platform_user_id', $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();
            $tenantRole = $hasSupportGrant ? 'support' : null;
        }

        return [
            ...parent::share($request),
            'name' => $tenant === null ? config('app.name') : $tenant->name,
            'auth' => [
                'user' => $user,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'currency' => $tenant->currency,
                'trialEndsAt' => $tenant->trial_ends_at,
                'role' => $tenantRole,
                'themeVersion' => $publishedTheme?->version,
                'themeAssets' => $publishedTheme === null ? [] : ($publishedTheme->configuration['assets'] ?? []),
            ] : null,
            'tenants' => fn () => $user?->tenants()
                ->wherePivot('status', 'active')
                ->get(['tenants.id', 'tenants.name', 'tenants.slug'])
                ->map(fn ($item) => [
                    'id' => $item->getKey(),
                    'name' => $item->getAttribute('name'),
                    'slug' => $item->getAttribute('slug'),
                    'role' => $item->getRelation('pivot')->getAttribute('role'),
                ]) ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'checkinResult' => fn () => $request->session()->get('checkinResult'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

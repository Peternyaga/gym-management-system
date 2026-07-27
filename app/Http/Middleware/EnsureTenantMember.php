<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMember
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->route('tenant');
        if ($request->attributes->get('tenant_role') === 'support') {
            abort_unless($request->isMethodSafe() && $this->supportMayRead($request), 403);

            return $next($request);
        }
        $membership = $request->user()?->tenants()
            ->whereKey($tenant->getKey())
            ->wherePivot('status', 'active')
            ->first();

        if ($membership === null && $request->user()?->is_platform_admin) {
            $hasSupportGrant = DB::table('support_access_grants')
                ->where('tenant_id', $tenant->id)
                ->where('platform_user_id', $request->user()->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();
            abort_unless($hasSupportGrant && $request->isMethodSafe() && $this->supportMayRead($request), 403);
            $request->attributes->set('tenant_role', 'support');
            AuditLog::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $request->user()->id,
                'event' => 'support_access.viewed',
                'subject_type' => 'route',
                'subject_id' => $request->route()?->getName(),
                'after' => ['path' => $request->path()],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $next($request);
        }

        abort_unless($membership !== null, 403);

        if ($roles !== []) {
            abort_unless(in_array($membership->pivot->getAttribute('role'), $roles, true), 403);
        }

        $request->attributes->set('tenant_role', $membership->pivot->getAttribute('role'));

        return $next($request);
    }

    private function supportMayRead(Request $request): bool
    {
        return ! in_array($request->route()?->getName(), [
            'tenant.exports.etims',
            'tenant.theme',
            'tenant.support-access',
        ], true);
    }
}

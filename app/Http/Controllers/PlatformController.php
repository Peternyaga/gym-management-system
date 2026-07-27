<?php

namespace App\Http\Controllers;

use App\Models\PaymentIntent;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePlatform($request);

        return Inertia::render('platform/dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'trialing' => Tenant::query()->where('status', 'trialing')->count(),
                'members' => DB::table('member_profiles')->whereNull('deleted_at')->count(),
                'paymentIncidents' => PaymentIntent::query()->withoutGlobalScopes()->where('status', 'pending_review')->count(),
            ],
            'tenants' => Tenant::query()
                ->withCount(['users'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Tenant $tenant) => [
                    ...$tenant->only(['id', 'name', 'slug', 'status', 'trial_ends_at', 'created_at']),
                    'usersCount' => $tenant->users_count,
                    'membersCount' => DB::table('member_profiles')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count(),
                    'lastSchedulerRun' => DB::table('scheduler_heartbeats')->where('name', 'gym-maintenance')->value('last_ran_at'),
                ]),
        ]);
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        $this->authorizePlatform($request);
        $activeSupportGrant = DB::table('support_access_grants')
            ->where('tenant_id', $tenant->id)
            ->where('platform_user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        return Inertia::render('platform/tenant', [
            'gym' => [
                ...$tenant->only([
                    'id', 'name', 'slug', 'status', 'currency', 'trial_ends_at',
                    'restricted_at', 'suspended_at', 'cancelled_at', 'created_at',
                ]),
                'publicUrl' => route('gym.public', $tenant),
                'staffUrl' => route('tenant.dashboard', $tenant),
                'hasActiveSupportGrant' => $activeSupportGrant !== null,
                'supportGrantExpiresAt' => $activeSupportGrant?->expires_at,
                'activeSupportGrants' => DB::table('support_access_grants')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->count(),
                'membersCount' => DB::table('member_profiles')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->count(),
                'staffCount' => DB::table('tenant_user')
                    ->where('tenant_id', $tenant->id)
                    ->where('role', '!=', 'member')
                    ->where('status', 'active')
                    ->count(),
                'locations' => $tenant->locations()->get(['id', 'name', 'timezone', 'is_active']),
                'staff' => $tenant->users()
                    ->wherePivot('status', 'active')
                    ->wherePivot('role', '!=', 'member')
                    ->get(['users.id', 'users.name', 'users.email'])
                    ->map(fn ($user) => [
                        ...$user->only(['id', 'name', 'email']),
                        'role' => $user->getRelation('pivot')->getAttribute('role'),
                    ]),
                'subscription' => DB::table('tenant_subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->latest('created_at')
                    ->first(),
                'themeVersions' => $tenant->themes()
                    ->withoutGlobalScopes()
                    ->latest('version')
                    ->limit(10)
                    ->get(['id', 'version', 'name', 'status', 'published_at', 'created_at']),
                'paymentIncidents' => PaymentIntent::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'pending_review')
                    ->count(),
                'lastSchedulerRun' => DB::table('scheduler_heartbeats')
                    ->where('name', 'gym-maintenance')
                    ->value('last_ran_at'),
            ],
            'recentAudit' => DB::table('audit_logs')
                ->where('tenant_id', $tenant->id)
                ->latest('created_at')
                ->limit(12)
                ->get(['id', 'event', 'subject_type', 'created_at']),
        ]);
    }

    public function updateStatus(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlatform($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['trialing', 'active', 'restricted', 'suspended', 'cancelled'])],
        ]);

        $before = $tenant->only(['status', 'restricted_at', 'suspended_at', 'cancelled_at']);
        $status = $validated['status'];
        $tenant->forceFill([
            'status' => $status,
            'restricted_at' => $status === 'restricted' ? now() : null,
            'suspended_at' => $status === 'suspended' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
        ])->save();
        $log = $audit->record('platform.tenant.status_updated', $tenant, $before, $tenant->only([
            'status', 'restricted_at', 'suspended_at', 'cancelled_at',
        ]), $request);
        $log->update(['tenant_id' => $tenant->id]);

        return back()->with('success', "Gym status changed to {$status}.");
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_platform_admin, 403);
    }
}

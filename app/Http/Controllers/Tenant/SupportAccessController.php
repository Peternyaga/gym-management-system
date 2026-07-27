<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportAccessController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('tenant/support-access', [
            'platformAdmins' => DB::table('users')
                ->where('is_platform_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'grants' => DB::table('support_access_grants')
                ->join('users as platform_users', 'platform_users.id', '=', 'support_access_grants.platform_user_id')
                ->join('users as owners', 'owners.id', '=', 'support_access_grants.granted_by')
                ->where('support_access_grants.tenant_id', $tenant->id)
                ->latest('support_access_grants.created_at')
                ->get([
                    'support_access_grants.id', 'support_access_grants.reason',
                    'support_access_grants.expires_at', 'support_access_grants.revoked_at',
                    'support_access_grants.created_at', 'platform_users.name as platform_name',
                    'platform_users.email as platform_email', 'owners.name as granted_by_name',
                ])
                ->map(fn ($grant) => [
                    ...get_object_vars($grant),
                    'expires_at' => Carbon::parse($grant->expires_at, 'UTC')->toIso8601String(),
                    'revoked_at' => $grant->revoked_at === null
                        ? null
                        : Carbon::parse($grant->revoked_at, 'UTC')->toIso8601String(),
                    'created_at' => Carbon::parse($grant->created_at, 'UTC')->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'platform_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('is_platform_admin', true),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'hours' => ['required', 'integer', 'between:1,24'],
        ]);
        $grantId = (string) str()->ulid();
        $expiresAt = now()->addHours((int) $data['hours']);

        DB::transaction(function () use ($request, $tenant, $data, $grantId, $expiresAt): void {
            DB::table('support_access_grants')
                ->where('tenant_id', $tenant->id)
                ->where('platform_user_id', $data['platform_user_id'])
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('support_access_grants')->insert([
                'id' => $grantId,
                'tenant_id' => $tenant->id,
                'granted_by' => $request->user()->id,
                'platform_user_id' => $data['platform_user_id'],
                'reason' => $data['reason'],
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            AuditLog::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $request->user()->id,
                'event' => 'support_access.granted',
                'subject_type' => 'support_access_grant',
                'subject_id' => $grantId,
                'after' => [
                    'platform_user_id' => $data['platform_user_id'],
                    'reason' => $data['reason'],
                    'expires_at' => $expiresAt,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('success', 'Time-limited read-only support access granted.');
    }

    public function destroy(Request $request, Tenant $tenant, string $grant): RedirectResponse
    {
        $record = DB::table('support_access_grants')
            ->where('tenant_id', $tenant->id)
            ->where('id', $grant)
            ->first();
        abort_unless($record !== null, 404);
        if ($record->revoked_at === null) {
            DB::transaction(function () use ($request, $tenant, $grant, $record): void {
                DB::table('support_access_grants')->where('id', $grant)->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
                AuditLog::query()->create([
                    'tenant_id' => $tenant->id,
                    'actor_id' => $request->user()->id,
                    'event' => 'support_access.revoked',
                    'subject_type' => 'support_access_grant',
                    'subject_id' => $grant,
                    'before' => ['expires_at' => $record->expires_at],
                    'after' => ['revoked_at' => now()],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });
        }

        return back()->with('success', 'Support access revoked.');
    }
}

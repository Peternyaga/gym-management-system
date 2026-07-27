<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Tenant $tenant): Response
    {
        $query = MemberProfile::query()->where('tenant_id', $tenant->id)->with('memberships')->latest();
        $search = trim((string) $request->query('search'));

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('member_number', 'like', "%{$search}%"));
        }

        return Inertia::render('tenant/members', [
            'members' => $query->limit(100)->get(),
            'search' => $search,
        ]);
    }

    public function store(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'adult_confirmed' => ['accepted'],
        ]);

        $data['phone'] = $this->normalizePhone($data['phone']);
        if (! empty($data['emergency_contact_phone'])) {
            $data['emergency_contact_phone'] = $this->normalizePhone($data['emergency_contact_phone']);
        }

        $location = $tenant->locations()->where('is_active', true)->firstOrFail();
        $sequence = (int) MemberProfile::query()->withTrashed()->where('tenant_id', $tenant->id)->count() + 1;
        $member = MemberProfile::query()->create([
            ...$data,
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_number' => 'MEM-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $audit->record('member.created', $member, null, $member->only(['name', 'phone', 'member_number']), $request);

        return back()->with('success', "Member {$member->member_number} created.");
    }

    public function show(Tenant $tenant, string $member): Response
    {
        $profile = MemberProfile::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($member)
            ->with(['memberships' => fn ($query) => $query->with('plan')->latest('starts_at')])
            ->firstOrFail();

        return Inertia::render('tenant/member-detail', [
            'member' => $profile,
            'invoices' => DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->where('member_id', $profile->id)
                ->latest('created_at')
                ->limit(20)
                ->get(),
            'bookings' => DB::table('bookings')
                ->join('class_instances', 'class_instances.id', '=', 'bookings.class_instance_id')
                ->where('bookings.tenant_id', $tenant->id)
                ->where('bookings.member_id', $profile->id)
                ->latest('class_instances.starts_at')
                ->limit(20)
                ->get([
                    'bookings.id', 'bookings.status', 'bookings.attended_at',
                    'class_instances.name', 'class_instances.starts_at',
                ]),
            'checkins' => DB::table('checkins')
                ->where('tenant_id', $tenant->id)
                ->where('member_id', $profile->id)
                ->latest('checked_in_at')
                ->limit(20)
                ->get(['id', 'result', 'method', 'denial_reason', 'checked_in_at']),
        ]);
    }

    public function update(Request $request, Tenant $tenant, string $member, AuditLogger $audit): RedirectResponse
    {
        $profile = MemberProfile::query()->where('tenant_id', $tenant->id)->whereKey($member)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'status' => ['required', Rule::in(['active', 'inactive', 'restricted'])],
        ]);
        $data['phone'] = $this->normalizePhone($data['phone']);
        if (! empty($data['emergency_contact_phone'])) {
            $data['emergency_contact_phone'] = $this->normalizePhone($data['emergency_contact_phone']);
        }
        $before = $profile->only(array_keys($data));
        $profile->update($data);
        $audit->record('member.updated', $profile, $before, $profile->only(array_keys($data)), $request);

        return back()->with('success', 'Member details updated.');
    }

    public function membershipAction(Request $request, Tenant $tenant, string $member, AuditLogger $audit): RedirectResponse
    {
        $profile = MemberProfile::query()->where('tenant_id', $tenant->id)->whereKey($member)->firstOrFail();
        $data = $request->validate([
            'membership_id' => ['required', 'string'],
            'action' => ['required', Rule::in(['pause', 'resume', 'cancel'])],
            'pause_days' => ['nullable', 'integer', 'between:1,365'],
        ]);
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('member_id', $profile->id)
            ->whereKey($data['membership_id'])
            ->firstOrFail();
        $before = $membership->only(['status', 'paused_at', 'pause_ends_at', 'ends_at', 'next_billing_at', 'cancelled_at']);

        if ($data['action'] === 'pause') {
            abort_unless(in_array($membership->status, ['active', 'grace'], true), 409, 'Only active memberships can be paused.');
            $days = (int) ($data['pause_days'] ?? 7);
            $membership->forceFill([
                'status' => 'paused',
                'paused_at' => now(),
                'pause_ends_at' => now()->addDays($days),
                'ends_at' => $membership->ends_at?->addDays($days),
                'next_billing_at' => $membership->next_billing_at?->addDays($days),
            ]);
        } elseif ($data['action'] === 'resume') {
            abort_unless($membership->status === 'paused', 409, 'Only paused memberships can be resumed.');
            $membership->forceFill(['status' => 'active', 'paused_at' => null, 'pause_ends_at' => null]);
        } else {
            abort_if(in_array($membership->status, ['cancelled', 'expired'], true), 409, 'Membership is already closed.');
            $membership->forceFill(['status' => 'cancelled', 'cancelled_at' => now()]);
        }
        $membership->save();
        $audit->record("membership.{$data['action']}", $membership, $before, $membership->only(array_keys($before)), $request);

        return back()->with('success', "Membership {$data['action']} completed.");
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        return str_starts_with($phone, '0') ? '+254'.substr($phone, 1) : $phone;
    }
}

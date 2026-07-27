<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\Tenant;
use App\Services\Attendance\RotatingQrToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckinController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        $today = now()->startOfDay();

        return Inertia::render('tenant/checkin', [
            'metrics' => [
                'acceptedToday' => Checkin::query()->where('tenant_id', $tenant->id)->where('result', 'accepted')
                    ->where('checked_in_at', '>=', $today)->count(),
                'deniedToday' => Checkin::query()->where('tenant_id', $tenant->id)->where('result', 'denied')
                    ->where('checked_in_at', '>=', $today)->count(),
                'uniqueToday' => Checkin::query()->where('tenant_id', $tenant->id)->where('result', 'accepted')
                    ->where('checked_in_at', '>=', $today)->distinct('member_id')->count('member_id'),
            ],
            'recent' => Checkin::query()
                ->where('checkins.tenant_id', $tenant->id)
                ->join('member_profiles', 'member_profiles.id', '=', 'checkins.member_id')
                ->latest('checked_in_at')
                ->limit(50)
                ->get(['checkins.id', 'member_profiles.name', 'member_profiles.member_number', 'checkins.result', 'checkins.denial_reason', 'checkins.checked_in_at']),
        ]);
    }

    public function show(Tenant $tenant, string $checkin): Response
    {
        $record = Checkin::query()->where('tenant_id', $tenant->id)->whereKey($checkin)->firstOrFail();

        return Inertia::render('tenant/checkin-detail', [
            'checkin' => $record,
            'member' => MemberProfile::query()->where('tenant_id', $tenant->id)->whereKey($record->member_id)
                ->firstOrFail(['id', 'name', 'member_number', 'phone', 'status']),
            'membership' => $record->membership_id
                ? Membership::query()->where('tenant_id', $tenant->id)->whereKey($record->membership_id)
                    ->with('plan')->first()
                : null,
            'location' => $tenant->locations()->whereKey($record->location_id)->first(['id', 'name', 'timezone']),
        ]);
    }

    public function store(Request $request, Tenant $tenant, RotatingQrToken $tokens): RedirectResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'max:120']]);
        $decoded = str_starts_with($data['query'], 'gymqr.') ? $tokens->verify($data['query']) : null;
        $member = MemberProfile::query()
            ->where('tenant_id', $tenant->id)
            ->when($decoded, fn ($query) => $query->whereKey(
                $decoded['tenant'] === $tenant->id ? $decoded['member'] : '__wrong_tenant__',
            ), fn ($query) => $query->where(
                fn ($match) => $match->where('member_number', $data['query'])->orWhere('phone', $data['query']),
            ))
            ->first();
        abort_unless($member !== null, 404, 'Member not found.');

        $duplicate = Checkin::query()
            ->where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('checked_in_at', '>=', now()->subMinutes(5))
            ->latest('checked_in_at')
            ->first();
        if ($duplicate) {
            return back()
                ->with('success', "Duplicate scan: {$member->name}'s original {$duplicate->result} result was returned.")
                ->with('checkinResult', [
                    'id' => $duplicate->id,
                    'member' => $member->name,
                    'number' => $member->member_number,
                    'result' => $duplicate->result,
                    'reason' => $duplicate->denial_reason,
                    'duplicate' => true,
                    'checkedInAt' => $duplicate->checked_in_at,
                ]);
        }

        $membership = Membership::query()
            ->where('member_id', $member->id)
            ->whereIn('status', ['active', 'grace'])
            ->latest('starts_at')
            ->first();
        $accepted = $membership !== null && ($membership->ends_at === null || $membership->ends_at->isFuture());

        $checkin = Checkin::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $member->location_id,
            'member_id' => $member->id,
            'membership_id' => $membership?->id,
            'method' => $decoded ? 'qr' : 'staff',
            'result' => $accepted ? 'accepted' : 'denied',
            'denial_reason' => $accepted ? null : 'No active membership',
            'checked_in_at' => now(),
        ]);

        return back()
            ->with($accepted ? 'success' : 'error', $accepted ? "Welcome, {$member->name}." : "{$member->name}: no active membership.")
            ->with('checkinResult', [
                'id' => $checkin->id,
                'member' => $member->name,
                'number' => $member->member_number,
                'result' => $checkin->result,
                'reason' => $checkin->denial_reason,
                'duplicate' => false,
                'checkedInAt' => $checkin->checked_in_at,
            ]);
    }
}

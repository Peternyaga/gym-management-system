<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Checkin;
use App\Models\ClassInstance;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Tenant $tenant): Response
    {
        $now = now();

        return Inertia::render('tenant/dashboard', [
            'metrics' => [
                'members' => MemberProfile::query()->where('tenant_id', $tenant->id)->count(),
                'activeMemberships' => Membership::query()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'grace'])->count(),
                'outstandingMinor' => Invoice::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['issued', 'partial', 'overdue'])
                    ->selectRaw('COALESCE(SUM(total_minor - paid_minor), 0) as total')
                    ->value('total'),
                'checkinsToday' => Checkin::query()->where('tenant_id', $tenant->id)->where('checked_in_at', '>=', $now->copy()->startOfDay())->count(),
            ],
            'classes' => ClassInstance::query()
                ->where('tenant_id', $tenant->id)
                ->where('starts_at', '>=', $now)
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (ClassInstance $class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'startsAt' => $class->starts_at,
                    'capacity' => $class->capacity,
                    'booked' => Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $class->id)->where('status', 'booked')->count(),
                ]),
            'recentCheckins' => Checkin::query()
                ->where('checkins.tenant_id', $tenant->id)
                ->join('member_profiles', 'member_profiles.id', '=', 'checkins.member_id')
                ->latest('checkins.checked_in_at')
                ->limit(6)
                ->get(['checkins.id', 'member_profiles.name', 'checkins.result', 'checkins.checked_in_at']),
        ]);
    }
}

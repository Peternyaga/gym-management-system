<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassInstance;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\Tenant;
use App\Services\Attendance\RotatingQrToken;
use App\Services\Themes\ThemeSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberPortalController extends Controller
{
    public function home(Request $request, Tenant $tenant, RotatingQrToken $tokens): Response
    {
        $member = MemberProfile::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->firstOrFail();
        $membership = Membership::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->latest()->first();
        $bookings = Booking::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->get()->keyBy('class_instance_id');
        $publishedTheme = $tenant->publishedTheme();
        $theme = ThemeSchema::normalize($publishedTheme === null ? [] : $publishedTheme->configuration);

        return Inertia::render('member/home', [
            'member' => $member,
            'membership' => $membership,
            'qrToken' => $tokens->issue($tenant, $member->id),
            'presentation' => [
                'layout' => $theme['memberLayout'],
                'content' => $theme['memberContent'],
            ],
            'balanceMinor' => Invoice::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('COALESCE(SUM(total_minor - paid_minor), 0) as total')->value('total'),
            'invoices' => Invoice::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->latest()->limit(10)->get(),
            'classes' => ClassInstance::query()->where('tenant_id', $tenant->id)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(12)->get()->map(fn (ClassInstance $class) => [
                ...$class->toArray(),
                'booking' => $bookings->get($class->id),
                'booked' => Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $class->id)->where('status', 'booked')->count(),
            ]),
        ]);
    }

    public function book(Request $request, Tenant $tenant, string $class): RedirectResponse
    {
        $member = MemberProfile::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->firstOrFail();
        $membership = Membership::query()->where('tenant_id', $tenant->id)->where('member_id', $member->id)->whereIn('status', ['active', 'grace'])->latest()->firstOrFail();

        DB::transaction(function () use ($tenant, $class, $member, $membership): void {
            $instance = ClassInstance::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($class);
            abort_if($instance->starts_at->isPast(), 409, 'Booking has closed.');
            $booked = Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $instance->id)->where('status', 'booked')->count();
            $status = $booked < $instance->capacity ? 'booked' : 'waitlisted';
            Booking::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'class_instance_id' => $instance->id, 'member_id' => $member->id],
                [
                    'membership_id' => $membership->id,
                    'status' => $status,
                    'waitlist_position' => $status === 'waitlisted'
                        ? Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $instance->id)->where('status', 'waitlisted')->max('waitlist_position') + 1
                        : null,
                    'booked_at' => now(),
                    'cancelled_at' => null,
                ],
            );
        });

        return back()->with('success', 'Booking updated.');
    }

    public function cancel(Request $request, Tenant $tenant, string $class): RedirectResponse
    {
        $member = MemberProfile::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->firstOrFail();
        DB::transaction(function () use ($tenant, $class, $member): void {
            $instance = ClassInstance::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($class);
            $booking = Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $class)->where('member_id', $member->id)->firstOrFail();
            $wasBooked = $booking->status === 'booked';
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            if ($wasBooked) {
                Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $instance->id)->where('status', 'waitlisted')->orderBy('waitlist_position')->lockForUpdate()->first()?->update([
                    'status' => 'booked',
                    'waitlist_position' => null,
                ]);
            }
        });

        return back()->with('success', 'Booking cancelled.');
    }
}

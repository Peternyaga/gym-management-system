<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassInstance;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('tenant/schedule', [
            'classes' => ClassInstance::query()
                ->where('tenant_id', $tenant->id)
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (ClassInstance $class) => [
                    ...$class->toArray(),
                    'booked' => Booking::query()->where('class_instance_id', $class->id)->where('status', 'booked')->count(),
                    'waitlisted' => Booking::query()->where('class_instance_id', $class->id)->where('status', 'waitlisted')->count(),
                ]),
        ]);
    }

    public function store(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'capacity' => ['required', 'integer', 'between:1,250'],
        ]);

        $class = DB::transaction(function () use ($tenant, $data): ClassInstance {
            $classType = DB::table('class_types')->where('tenant_id', $tenant->id)->where('name', $data['name'])->first();
            $classTypeId = $classType ? $classType->id : (string) str()->ulid();
            if (! $classType) {
                DB::table('class_types')->insert([
                    'id' => $classTypeId,
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'default_duration_minutes' => $data['duration_minutes'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $start = now()->parse($data['starts_at'])->utc();

            return ClassInstance::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $tenant->locations()->where('is_active', true)->value('id'),
                'class_type_id' => $classTypeId,
                'name' => $data['name'],
                'capacity' => $data['capacity'],
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($data['duration_minutes']),
                'status' => 'scheduled',
            ]);
        });
        $audit->record('class.created', $class, null, $class->toArray(), $request);

        return back()->with('success', 'Class added to the schedule.');
    }

    public function show(Tenant $tenant, string $class): Response
    {
        $record = ClassInstance::query()->where('tenant_id', $tenant->id)->whereKey($class)->firstOrFail();

        return Inertia::render('tenant/class-detail', [
            'classItem' => $record,
            'roster' => Booking::query()
                ->where('bookings.tenant_id', $tenant->id)
                ->where('class_instance_id', $record->id)
                ->join('member_profiles', 'member_profiles.id', '=', 'bookings.member_id')
                ->orderByRaw("CASE WHEN bookings.status = 'booked' THEN 0 ELSE 1 END")
                ->orderBy('bookings.waitlist_position')
                ->get([
                    'bookings.id', 'bookings.status', 'bookings.waitlist_position',
                    'bookings.booked_at', 'bookings.attended_at', 'member_profiles.id as member_id',
                    'member_profiles.name', 'member_profiles.member_number',
                ]),
        ]);
    }

    public function update(Request $request, Tenant $tenant, string $class, AuditLogger $audit): RedirectResponse
    {
        $record = ClassInstance::query()->where('tenant_id', $tenant->id)->whereKey($class)->firstOrFail();
        abort_unless($record->status === 'scheduled', 409, 'Only scheduled classes can be edited.');
        $booked = Booking::query()->where('tenant_id', $tenant->id)->where('class_instance_id', $record->id)
            ->where('status', 'booked')->count();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'capacity' => ['required', 'integer', "min:{$booked}", 'max:250'],
        ]);
        $start = now()->parse($data['starts_at'])->utc();
        $before = $record->toArray();
        $record->update([
            'name' => $data['name'],
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes((int) $data['duration_minutes']),
            'capacity' => $data['capacity'],
        ]);
        $audit->record('class.updated', $record, $before, $record->toArray(), $request);

        return back()->with('success', 'Class details updated.');
    }

    public function cancel(Request $request, Tenant $tenant, string $class, AuditLogger $audit): RedirectResponse
    {
        $record = ClassInstance::query()->where('tenant_id', $tenant->id)->whereKey($class)->firstOrFail();
        abort_if($record->status === 'cancelled', 409, 'Class is already cancelled.');
        DB::transaction(function () use ($record): void {
            $record->update(['status' => 'cancelled']);
            Booking::query()->where('tenant_id', $record->tenant_id)
                ->where('class_instance_id', $record->id)
                ->whereIn('status', ['booked', 'waitlisted'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
        $audit->record('class.cancelled', $record, ['status' => 'scheduled'], ['status' => 'cancelled'], $request);

        return redirect()->route('tenant.schedule', $tenant)->with('success', 'Class cancelled and active bookings released.');
    }

    public function attendance(Request $request, Tenant $tenant, string $class, string $booking, AuditLogger $audit): RedirectResponse
    {
        $record = ClassInstance::query()->where('tenant_id', $tenant->id)->whereKey($class)->firstOrFail();
        $reservation = Booking::query()->where('tenant_id', $tenant->id)
            ->where('class_instance_id', $record->id)->whereKey($booking)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['attended', 'no_show'])]]);
        $before = $reservation->only(['status', 'attended_at']);
        $reservation->update([
            'status' => $data['status'],
            'attended_at' => $data['status'] === 'attended' ? now() : null,
        ]);
        $audit->record('booking.attendance_updated', $reservation, $before, $reservation->only(['status', 'attended_at']), $request);

        return back()->with('success', 'Attendance updated.');
    }
}

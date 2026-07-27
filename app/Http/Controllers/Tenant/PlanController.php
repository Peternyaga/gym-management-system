<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        return Inertia::render('tenant/plans', [
            'plans' => MembershipPlan::query()->where('tenant_id', $tenant->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:recurring,fixed,pack'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'required_if:type,recurring', 'in:weekly,monthly,annual'],
            'duration_days' => ['nullable', 'required_if:type,fixed', 'integer', 'min:1'],
            'pack_credits' => ['nullable', 'required_if:type,pack', 'integer', 'min:1'],
        ]);
        $priceMinor = (int) round(((float) $data['price']) * 100);
        unset($data['price']);

        $plan = MembershipPlan::query()->create([
            ...$data,
            'tenant_id' => $tenant->id,
            'location_id' => $tenant->locations()->where('is_active', true)->value('id'),
            'price_minor' => $priceMinor,
            'joining_fee_minor' => 0,
            'interval_count' => 1,
            'credit_scope' => $data['type'] === 'pack' ? 'class_credit' : null,
            'pack_expiry_days' => $data['type'] === 'pack' ? 90 : null,
            'grace_days' => 3,
            'is_public' => true,
            'is_active' => true,
        ]);
        $audit->record('plan.created', $plan, null, $plan->toArray(), $request);

        return back()->with('success', 'Membership plan created.');
    }

    public function show(Tenant $tenant, string $plan): Response
    {
        $record = MembershipPlan::query()->where('tenant_id', $tenant->id)->whereKey($plan)->firstOrFail();

        return Inertia::render('tenant/plan-detail', [
            'plan' => $record,
            'usage' => [
                'total' => \App\Models\Membership::query()->where('tenant_id', $tenant->id)
                    ->where('plan_id', $record->id)->count(),
                'active' => \App\Models\Membership::query()->where('tenant_id', $tenant->id)
                    ->where('plan_id', $record->id)->whereIn('status', ['active', 'grace', 'paused'])->count(),
            ],
        ]);
    }

    public function update(Request $request, Tenant $tenant, string $plan, AuditLogger $audit): RedirectResponse
    {
        $record = MembershipPlan::query()->where('tenant_id', $tenant->id)->whereKey($plan)->firstOrFail();
        $data = $this->validatedPlan($request, $tenant, $record->id);
        $before = $record->toArray();
        $priceMinor = (int) round(((float) $data['price']) * 100);
        unset($data['price']);
        $record->update([
            ...$data,
            'price_minor' => $priceMinor,
            'duration_days' => $data['type'] === 'fixed' ? $data['duration_days'] : null,
            'pack_credits' => $data['type'] === 'pack' ? $data['pack_credits'] : null,
            'billing_interval' => $data['type'] === 'recurring' ? $data['billing_interval'] : null,
        ]);
        $audit->record('plan.updated', $record, $before, $record->toArray(), $request);

        return back()->with('success', 'Plan updated. Existing membership snapshots are unchanged.');
    }

    public function destroy(Request $request, Tenant $tenant, string $plan, AuditLogger $audit): RedirectResponse
    {
        $record = MembershipPlan::query()->where('tenant_id', $tenant->id)->whereKey($plan)->firstOrFail();
        $hasMemberships = \App\Models\Membership::query()->where('tenant_id', $tenant->id)->where('plan_id', $record->id)->exists();
        if ($hasMemberships) {
            $before = $record->only(['is_active', 'is_public']);
            $record->update(['is_active' => false, 'is_public' => false]);
            $audit->record('plan.archived', $record, $before, $record->only(['is_active', 'is_public']), $request);

            return redirect()->route('tenant.plans', $tenant)->with('success', 'Plan archived because membership history references it.');
        }
        $audit->record('plan.deleted', $record, $record->toArray(), null, $request);
        $record->delete();

        return redirect()->route('tenant.plans', $tenant)->with('success', 'Unused plan removed.');
    }

    private function validatedPlan(Request $request, Tenant $tenant, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('membership_plans')->where('tenant_id', $tenant->id)->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:recurring,fixed,pack'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'required_if:type,recurring', 'in:weekly,monthly,annual'],
            'duration_days' => ['nullable', 'required_if:type,fixed', 'integer', 'min:1'],
            'pack_credits' => ['nullable', 'required_if:type,pack', 'integer', 'min:1'],
            'grace_days' => ['required', 'integer', 'between:0,30'],
            'is_public' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}

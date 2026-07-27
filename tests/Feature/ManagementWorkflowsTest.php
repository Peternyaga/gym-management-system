<?php

namespace Tests\Feature;

use App\Models\ClassInstance;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_open_and_manage_a_tenant(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Managed Gym');

        $this->actingAs($admin)
            ->get(route('platform.tenants.show', $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/tenant')
                ->where('tenant', null)
                ->where('gym.slug', $tenant->slug));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('platform.tenants.status', $tenant), ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'platform.tenant.status_updated',
        ]);
    }

    public function test_owner_can_open_member_and_manage_membership(): void
    {
        [$owner, $tenant, $location] = $this->tenant();
        $plan = $this->plan($tenant->id, $location->id);
        $member = MemberProfile::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_number' => 'MEM-00001',
            'name' => 'Test Member',
            'phone' => '+254712345678',
            'adult_confirmed' => true,
            'status' => 'active',
        ]);
        $membership = Membership::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'plan_snapshot' => $plan->toArray(),
            'starts_at' => now(),
            'next_billing_at' => now()->addMonth(),
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.members.show', [$tenant, $member->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/member-detail')
                ->where('member.member_number', 'MEM-00001'));

        $this->actingAs($owner)
            ->post("/t/{$tenant->slug}/members/{$member->id}/membership-action", [
                'membership_id' => $membership->id,
                'action' => 'pause',
                'pause_days' => 7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', ['id' => $membership->id, 'status' => 'paused']);
    }

    public function test_owner_can_edit_and_archive_a_referenced_plan(): void
    {
        [$owner, $tenant, $location] = $this->tenant();
        $plan = $this->plan($tenant->id, $location->id);
        $member = MemberProfile::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_number' => 'MEM-00002',
            'name' => 'Plan Member',
            'phone' => '+254722345678',
            'adult_confirmed' => true,
            'status' => 'active',
        ]);
        Membership::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'plan_snapshot' => $plan->toArray(),
            'starts_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch("/t/{$tenant->slug}/plans/{$plan->id}", [
                'name' => 'Updated Plan',
                'description' => 'New offer',
                'type' => 'recurring',
                'price' => '6000',
                'billing_interval' => 'monthly',
                'duration_days' => null,
                'pack_credits' => null,
                'grace_days' => 4,
                'is_public' => true,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('membership_plans', [
            'id' => $plan->id,
            'name' => 'Updated Plan',
            'price_minor' => 600000,
        ]);

        $this->actingAs($owner)->delete("/t/{$tenant->slug}/plans/{$plan->id}")->assertRedirect();
        $this->assertDatabaseHas('membership_plans', [
            'id' => $plan->id,
            'is_active' => false,
            'is_public' => false,
        ]);
    }

    public function test_owner_can_update_and_cancel_a_class(): void
    {
        [$owner, $tenant, $location] = $this->tenant();
        $classType = (string) str()->ulid();
        DB::table('class_types')->insert([
            'id' => $classType,
            'tenant_id' => $tenant->id,
            'name' => 'Circuit',
            'default_duration_minutes' => 60,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $class = ClassInstance::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'class_type_id' => $classType,
            'name' => 'Circuit',
            'capacity' => 20,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($owner)
            ->patch("/t/{$tenant->slug}/schedule/{$class->id}", [
                'name' => 'Strength Circuit',
                'starts_at' => now()->addDays(2)->toIso8601String(),
                'duration_minutes' => 45,
                'capacity' => 16,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('class_instances', ['id' => $class->id, 'name' => 'Strength Circuit', 'capacity' => 16]);

        $this->actingAs($owner)->delete("/t/{$tenant->slug}/schedule/{$class->id}")->assertRedirect();
        $this->assertDatabaseHas('class_instances', ['id' => $class->id, 'status' => 'cancelled']);
    }

    public function test_shared_tenant_role_is_available_before_pages_render(): void
    {
        [$owner, $tenant, $location] = $this->tenant();
        $this->actingAs($owner)
            ->get(route('tenant.dashboard', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.role', 'owner'));

        foreach (['manager', 'receptionist', 'trainer'] as $role) {
            $user = User::factory()->create();
            $tenant->users()->attach($user->id, [
                'role' => $role,
                'status' => 'active',
                'location_id' => $location->id,
                'joined_at' => now(),
            ]);
            $this->actingAs($user)
                ->get($role === 'trainer' ? route('tenant.schedule', $tenant) : route('tenant.dashboard', $tenant))
                ->assertInertia(fn (Assert $page) => $page->where('tenant.role', $role));
        }

        $memberUser = User::factory()->create();
        $tenant->users()->attach($memberUser->id, [
            'role' => 'member',
            'status' => 'active',
            'location_id' => $location->id,
            'joined_at' => now(),
        ]);
        MemberProfile::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'user_id' => $memberUser->id,
            'member_number' => 'MEM-ROLE',
            'name' => 'Portal Member',
            'phone' => '+254733345678',
            'adult_confirmed' => true,
            'status' => 'active',
        ]);
        $this->actingAs($memberUser)
            ->get(route('member.home', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.role', 'member'));
    }

    public function test_owner_approved_support_grant_is_expiring_and_read_only(): void
    {
        [$owner, $tenant] = $this->tenant();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $grant = (string) str()->ulid();
        DB::table('support_access_grants')->insert([
            'id' => $grant,
            'tenant_id' => $tenant->id,
            'granted_by' => $owner->id,
            'platform_user_id' => $admin->id,
            'reason' => 'Investigate a tenant dashboard issue.',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.support-access', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->has('grants', 1)
                ->where('grants.0.expires_at', fn (string $value) => str_contains($value, '+00:00')));

        $this->actingAs($admin)
            ->get(route('tenant.dashboard', $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tenant.role', 'support'));

        $this->actingAs($admin)
            ->post("/t/{$tenant->slug}/members", [
                'name' => 'Forbidden Write',
                'phone' => '+254744345678',
                'adult_confirmed' => true,
            ])
            ->assertForbidden();

        DB::table('support_access_grants')->where('id', $grant)->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($admin)->get(route('tenant.dashboard', $tenant))->assertForbidden();
    }

    public function test_payment_and_invoice_details_project_allocations_in_both_directions(): void
    {
        [$owner, $tenant, $location] = $this->tenant();
        $member = MemberProfile::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_number' => 'MEM-PAY',
            'name' => 'Paying Member',
            'phone' => '+254755345678',
            'adult_confirmed' => true,
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'member_id' => $member->id,
            'number' => 'INV-ALLOC-1',
            'status' => 'paid',
            'currency' => 'KES',
            'subtotal_minor' => 500000,
            'total_minor' => 500000,
            'paid_minor' => 500000,
            'issued_at' => now(),
            'paid_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'tenant_id' => $tenant->id,
            'source' => 'mpesa',
            'status' => 'confirmed',
            'receipt_number' => 'ALLOC123',
            'amount_minor' => 500000,
            'unapplied_minor' => 0,
            'paid_at' => now(),
        ]);
        DB::table('payment_allocations')->insert([
            'id' => (string) str()->ulid(),
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_minor' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.invoices.show', [$tenant, $invoice->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('allocations', 1)
                ->where('allocations.0.receipt_number', 'ALLOC123')
                ->where('integrityWarning', null));

        $this->actingAs($owner)
            ->get(route('tenant.payments.show', [$tenant, $payment->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('allocations', 1)
                ->where('allocations.0.number', 'INV-ALLOC-1')
                ->where('integrityWarning', null));
    }

    private function tenant(): array
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Workflow Gym');

        return [$owner, $tenant, $tenant->locations()->firstOrFail()];
    }

    private function plan(string $tenantId, string $locationId): MembershipPlan
    {
        return MembershipPlan::query()->create([
            'tenant_id' => $tenantId,
            'location_id' => $locationId,
            'name' => 'Open Gym',
            'type' => 'recurring',
            'price_minor' => 450000,
            'joining_fee_minor' => 0,
            'billing_interval' => 'monthly',
            'interval_count' => 1,
            'grace_days' => 3,
            'is_public' => true,
            'is_active' => true,
        ]);
    }
}

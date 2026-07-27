<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_adult_can_verify_a_kenyan_phone_and_create_a_pending_membership(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Signup Gym');
        $location = $tenant->locations()->first();
        $plan = MembershipPlan::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'name' => 'Monthly',
            'type' => 'recurring',
            'price_minor' => 300000,
            'billing_interval' => 'monthly',
            'joining_fee_minor' => 0,
            'interval_count' => 1,
            'grace_days' => 3,
            'is_public' => true,
            'is_active' => true,
        ]);

        $otp = $this->postJson("/g/{$tenant->slug}/otp", ['phone' => '0712345678'])
            ->assertOk()
            ->json();
        $this->post("/g/{$tenant->slug}/join", [
            'verification_id' => $otp['verificationId'],
            'otp' => $otp['developmentCode'],
            'plan_id' => $plan->id,
            'name' => 'Jane Wanjiku',
            'phone' => '0712345678',
            'email' => 'jane@example.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'emergency_contact_name' => 'John Wanjiku',
            'emergency_contact_phone' => '0722000000',
            'adult_confirmed' => true,
            'privacy_accepted' => true,
            'membership_terms_accepted' => true,
            'waiver_accepted' => true,
            'notifications_accepted' => true,
        ])->assertRedirect(route('gym.public', $tenant->slug));

        $this->assertDatabaseHas('member_profiles', ['tenant_id' => $tenant->id, 'phone' => '+254712345678']);
        $this->assertDatabaseHas('memberships', ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'pending']);
        $this->assertDatabaseHas('invoices', ['tenant_id' => $tenant->id, 'total_minor' => 300000, 'status' => 'issued']);
        $this->assertDatabaseCount('member_consents', 4);
    }
}

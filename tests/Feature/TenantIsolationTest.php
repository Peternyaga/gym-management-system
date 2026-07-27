<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_open_another_tenants_operational_routes(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        app(TenantProvisioner::class)->provision($firstOwner, 'First Gym');
        $second = app(TenantProvisioner::class)->provision($secondOwner, 'Second Gym');

        $this->actingAs($firstOwner)
            ->get(route('tenant.members', $second->slug))
            ->assertForbidden();
    }

    public function test_manager_cannot_publish_a_theme(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Access Gym');
        $tenant->users()->attach($manager->id, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);

        $this->actingAs($manager)
            ->post("/t/{$tenant->slug}/settings/theme/publish")
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($user, 'Test Gym');
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('tenant.dashboard', $tenant->slug));
    }
}

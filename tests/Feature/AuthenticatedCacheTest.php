<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_are_never_stored_by_browser_or_proxy_caches(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Private Gym');

        $this->actingAs($owner)
            ->get(route('tenant.dashboard', $tenant->slug))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');
    }

    public function test_logout_invalidates_session_and_clears_browser_cache(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Logout Gym');

        $this->actingAs($owner)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();

        $this->get(route('tenant.dashboard', $tenant->slug))
            ->assertRedirect(route('login'));
    }
}

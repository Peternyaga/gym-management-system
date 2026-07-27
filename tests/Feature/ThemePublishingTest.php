<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantProvisioner;
use App\Services\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_drafts_are_private_until_atomically_published(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Pamoja Gym');
        $this->actingAs($owner);
        $manager = app(ThemeManager::class);
        $publishedId = $tenant->published_theme_id;
        $draft = $manager->draft($tenant, $owner->id);
        $configuration = $draft->configuration;
        $configuration['colors']['light']['accent'] = '#FFCC00';
        $manager->updateDraft($draft, $configuration);

        $this->assertSame($publishedId, $tenant->fresh()->published_theme_id);
        $this->assertSame('draft', $draft->fresh()->status);

        $manager->publish($tenant->fresh(), $draft->fresh(), $owner->id);

        $this->assertSame($draft->id, $tenant->fresh()->published_theme_id);
        $this->assertSame('published', $draft->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'event' => 'theme.published']);
    }

    public function test_generated_css_never_contains_injected_declarations(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Safe Gym');
        $manager = app(ThemeManager::class);
        $draft = $manager->draft($tenant, $owner->id);
        $configuration = $draft->configuration;
        $configuration['colors']['light']['brand'] = '#000000;background:url(https://evil.test)';

        $css = $manager->css($manager->updateDraft($draft, $configuration));

        $this->assertStringNotContainsString('evil.test', $css);
        $this->assertStringContainsString('--primary:#151A16', $css);
    }

    public function test_legacy_theme_drafts_are_normalized_before_the_editor_renders(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Legacy Theme Gym');
        $published = $tenant->publishedTheme();
        $legacy = $published->configuration;
        unset($legacy['memberLayout'], $legacy['memberContent']);
        $published->update(['configuration' => $legacy]);

        $draft = app(ThemeManager::class)->draft($tenant, $owner->id);

        $this->assertSame('statusFirst', $draft->configuration['memberLayout']);
        $this->assertSame('Welcome back', $draft->configuration['memberContent']['welcomeTitle']);

        $this->actingAs($owner)
            ->get(route('tenant.theme', $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/theme-editor')
                ->where('draft.configuration.memberContent.welcomeTitle', 'Welcome back'));
    }

    public function test_theme_validation_returns_an_inertia_compatible_redirect(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantProvisioner::class)->provision($owner, 'Validation Gym');
        app(ThemeManager::class)->draft($tenant, $owner->id);

        $this->actingAs($owner)
            ->from(route('tenant.theme', $tenant))
            ->post("/t/{$tenant->slug}/settings/theme/validate")
            ->assertRedirect(route('tenant.theme', $tenant))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_themes', [
            'tenant_id' => $tenant->id,
            'status' => 'draft',
        ]);
    }
}

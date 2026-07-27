<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Themes\ThemeAssetProcessor;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeSchema;
use App\Services\Themes\ThemeValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function edit(Request $request, Tenant $tenant, ThemeManager $manager): Response
    {
        $draft = $manager->draft($tenant, $request->user()->id);

        return Inertia::render('tenant/theme-editor', [
            'draft' => $draft,
            'draftAssets' => $draft->assets()->whereNull('deleted_at')->get()->map(fn ($asset) => [
                ...$asset->only(['id', 'type', 'alt_text', 'focal_x', 'focal_y', 'width', 'height']),
                'url' => Storage::disk('public')->url($asset->path),
            ]),
            'published' => $tenant->publishedTheme(),
            'history' => $tenant->themes()->whereIn('status', ['published', 'archived'])->latest('version')->get(),
            'options' => [
                'modes' => ThemeSchema::MODES,
                'typography' => ThemeSchema::TYPOGRAPHY,
                'radii' => ThemeSchema::RADII,
                'densities' => ThemeSchema::DENSITIES,
                'navigation' => ThemeSchema::NAVIGATION,
                'layouts' => ThemeSchema::LAYOUTS,
                'memberLayouts' => ThemeSchema::MEMBER_LAYOUTS,
            ],
        ]);
    }

    public function createDraft(Request $request, Tenant $tenant, ThemeManager $manager): RedirectResponse
    {
        $manager->draft($tenant, $request->user()->id);

        return back();
    }

    public function update(Request $request, Tenant $tenant, ThemeManager $manager): RedirectResponse
    {
        $draft = $tenant->themes()->where('status', 'draft')->firstOrFail();
        $configuration = $request->validate(['configuration' => ['required', 'array']])['configuration'];
        $manager->updateDraft($draft, $configuration);

        return back()->with('success', 'Draft saved. Live pages are unchanged.');
    }

    public function validateTheme(Tenant $tenant, ThemeValidator $validator): RedirectResponse
    {
        $draft = $tenant->themes()->where('status', 'draft')->firstOrFail();
        $validation = $validator->validate($draft->configuration);
        $draft->update(['validation_summary' => $validation]);

        return back()->with(
            $validation['valid'] ? 'success' : 'error',
            $validation['valid']
                ? 'Theme validation passed. The draft is ready to publish.'
                : count($validation['errors']).' theme validation issue(s) must be fixed.',
        );
    }

    public function uploadAsset(Request $request, Tenant $tenant, ThemeAssetProcessor $processor): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:lightLogo,darkLogo,appIcon,favicon,heroImage,kioskBackground'],
            'file' => ['required', 'file', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'focal_y' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $draft = $tenant->themes()->where('status', 'draft')->firstOrFail();
        $processor->store(
            $tenant,
            $draft,
            $data['type'],
            $data['file'],
            $data['alt_text'] ?? null,
            (float) ($data['focal_x'] ?? 50),
            (float) ($data['focal_y'] ?? 50),
        );

        return back()->with('success', 'Image processed and added to the draft.');
    }

    public function deleteAsset(Tenant $tenant, string $asset, ThemeAssetProcessor $processor): RedirectResponse
    {
        $draft = $tenant->themes()->where('status', 'draft')->firstOrFail();
        $model = $draft->assets()->whereKey($asset)->firstOrFail();
        $processor->remove($tenant, $draft, $model);

        return back()->with('success', 'Image removed from the draft. Recovery files are retained.');
    }

    public function publish(Request $request, Tenant $tenant, ThemeManager $manager): RedirectResponse
    {
        $draft = $tenant->themes()->where('status', 'draft')->firstOrFail();
        $manager->publish($tenant, $draft, $request->user()->id);

        return back()->with('success', "Theme version {$draft->version} is now live.");
    }

    public function rollback(Request $request, Tenant $tenant, int $version, ThemeManager $manager): RedirectResponse
    {
        $source = $tenant->themes()->where('version', $version)->whereIn('status', ['published', 'archived'])->firstOrFail();
        $published = $manager->rollback($tenant, $source, $request->user()->id);

        return back()->with('success', "Rolled back as new version {$published->version}.");
    }

    public function css(Tenant $tenant, int $version, ThemeManager $manager): HttpResponse
    {
        $theme = $tenant->themes()->where('version', $version)->whereIn('status', ['published', 'archived'])->firstOrFail();

        return response($manager->css($theme), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function manifest(Tenant $tenant): JsonResponse
    {
        $theme = $tenant->publishedTheme();
        $config = ThemeSchema::normalize($theme === null ? [] : $theme->configuration);

        return response()->json([
            'id' => "/t/{$tenant->slug}",
            'name' => $tenant->name,
            'short_name' => mb_substr($tenant->name, 0, 24),
            'start_url' => "/t/{$tenant->slug}/dashboard",
            'scope' => "/t/{$tenant->slug}/",
            'display' => 'standalone',
            'background_color' => $config['colors']['light']['background'],
            'theme_color' => $config['colors']['light']['brand'],
            'icons' => $config['assets']['appIcon']
                ? [
                    ['src' => $config['assets']['appIcon'], 'sizes' => '512x512', 'type' => 'image/webp', 'purpose' => 'any maskable'],
                ]
                : [
                    ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
                ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}

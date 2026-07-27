<?php

namespace App\Http\Controllers;

use App\Models\ClassInstance;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Services\Themes\ThemeSchema;
use Inertia\Inertia;
use Inertia\Response;

class PublicGymController extends Controller
{
    public function __invoke(Tenant $tenant): Response
    {
        $theme = $tenant->publishedTheme();
        $configuration = $theme === null ? [] : $theme->configuration;

        return Inertia::render('public/gym', [
            'gym' => $tenant->only(['id', 'name', 'slug', 'currency']),
            'theme' => [
                'version' => $theme?->version,
                'configuration' => ThemeSchema::normalize($configuration),
            ],
            'plans' => MembershipPlan::query()->where('tenant_id', $tenant->id)->where('is_active', true)->where('is_public', true)->orderBy('price_minor')->get(),
            'classes' => ClassInstance::query()->where('tenant_id', $tenant->id)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(6)->get(),
        ]);
    }
}

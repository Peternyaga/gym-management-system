<?php

namespace App\Services\Themes;

use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeManager
{
    public function __construct(
        private readonly ThemeValidator $validator,
        private readonly ThemeCssGenerator $css,
        private readonly AuditLogger $audit,
    ) {}

    public function createInitialTheme(Tenant $tenant, int $userId): TenantTheme
    {
        return $tenant->themes()->create([
            'version' => 1,
            'schema_version' => 1,
            'name' => ThemeSchema::DEFAULT['name'],
            'status' => 'published',
            'configuration' => ThemeSchema::DEFAULT,
            'validation_summary' => $this->validator->validate(ThemeSchema::DEFAULT),
            'created_by' => $userId,
            'published_by' => $userId,
            'published_at' => now(),
        ]);
    }

    public function draft(Tenant $tenant, int $userId): TenantTheme
    {
        $existing = $tenant->themes()->where('status', 'draft')->latest('version')->first();

        if ($existing) {
            $normalized = ThemeSchema::normalize($existing->configuration);
            if ($normalized !== $existing->configuration) {
                $existing->update([
                    'name' => $normalized['name'],
                    'configuration' => $normalized,
                    'validation_summary' => null,
                ]);
            }

            return $existing->refresh();
        }

        $source = $tenant->publishedTheme();
        $version = ((int) $tenant->themes()->max('version')) + 1;

        return $tenant->themes()->create([
            'version' => $version,
            'schema_version' => 1,
            'name' => ($source === null ? 'Gym theme' : $source->name).' draft',
            'status' => 'draft',
            'configuration' => ThemeSchema::normalize($source === null ? [] : $source->configuration),
            'source_theme_id' => $source?->getKey(),
            'created_by' => $userId,
        ]);
    }

    public function updateDraft(TenantTheme $theme, array $configuration): TenantTheme
    {
        abort_unless($theme->status === 'draft', 409, 'Published themes are immutable.');

        $theme->update([
            'name' => ThemeSchema::normalize($configuration)['name'],
            'configuration' => ThemeSchema::normalize($configuration),
            'validation_summary' => null,
        ]);

        return $theme->refresh();
    }

    public function publish(Tenant $tenant, TenantTheme $theme, int $userId): TenantTheme
    {
        abort_unless($theme->tenant_id === $tenant->getKey() && $theme->status === 'draft', 404);
        $validation = $this->validator->validate($theme->configuration);

        if (! $validation['valid']) {
            throw ValidationException::withMessages(['theme' => $validation['errors']]);
        }

        return DB::transaction(function () use ($tenant, $theme, $userId, $validation): TenantTheme {
            $tenant->themes()->where('status', 'published')->update(['status' => 'archived']);
            $theme->update([
                'status' => 'published',
                'validation_summary' => $validation,
                'published_by' => $userId,
                'published_at' => now(),
            ]);
            $tenant->update(['published_theme_id' => $theme->getKey()]);
            Cache::forget("tenant-theme:{$tenant->getKey()}");
            $this->audit->record('theme.published', $theme, null, [
                'version' => $theme->version,
                'name' => $theme->name,
            ], request());

            return $theme->refresh();
        });
    }

    public function rollback(Tenant $tenant, TenantTheme $source, int $userId): TenantTheme
    {
        abort_unless($source->tenant_id === $tenant->getKey() && $source->status !== 'draft', 404);
        $draft = $tenant->themes()->create([
            'version' => ((int) $tenant->themes()->max('version')) + 1,
            'schema_version' => $source->schema_version,
            'name' => "{$source->name} rollback",
            'status' => 'draft',
            'configuration' => ThemeSchema::normalize($source->configuration),
            'source_theme_id' => $source->getKey(),
            'created_by' => $userId,
        ]);

        return $this->publish($tenant, $draft, $userId);
    }

    public function css(TenantTheme $theme): string
    {
        return $this->css->generate($theme->configuration);
    }
}

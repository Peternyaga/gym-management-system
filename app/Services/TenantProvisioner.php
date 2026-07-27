<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Themes\ThemeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioner
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function provision(User $owner, string $gymName): Tenant
    {
        return DB::transaction(function () use ($owner, $gymName): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $gymName,
                'slug' => $this->uniqueSlug($gymName),
                'status' => 'trialing',
                'currency' => 'KES',
                'timezone' => 'Africa/Nairobi',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $location = $tenant->locations()->create([
                'name' => 'Main gym',
                'email' => $owner->email,
                'phone' => $owner->phone,
            ]);

            $tenant->users()->attach($owner->getKey(), [
                'role' => 'owner',
                'status' => 'active',
                'location_id' => $location->getKey(),
                'joined_at' => now(),
            ]);

            $theme = $this->themes->createInitialTheme($tenant, $owner->getKey());
            $tenant->update(['published_theme_id' => $theme->getKey()]);

            return $tenant->refresh();
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'gym';
        $slug = $base;
        $counter = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}

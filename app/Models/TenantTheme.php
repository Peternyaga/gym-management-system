<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property int $version
 * @property string $name
 * @property string $status
 * @property array<string, mixed> $configuration
 * @property array<string, mixed>|null $validation_summary
 */
class TenantTheme extends Model
{
    use BelongsToTenant, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'validation_summary' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<ThemeAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(ThemeAsset::class, 'theme_id');
    }
}

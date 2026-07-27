<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $currency
 * @property string|null $published_theme_id
 * @property Carbon|null $trial_ends_at
 */
class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'restricted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return HasMany<TenantTheme, $this> */
    public function themes(): HasMany
    {
        return $this->hasMany(TenantTheme::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status', 'location_id', 'joined_at'])
            ->withTimestamps();
    }

    public function publishedTheme(): ?TenantTheme
    {
        return $this->published_theme_id
            ? $this->themes()->withoutGlobalScopes()->whereKey($this->published_theme_id)->first()
            : null;
    }
}

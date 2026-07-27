<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenant = app(TenantContext::class)->current();

            if ($tenant !== null) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenant->getKey());
            }
        });

        static::creating(function ($model): void {
            if (empty($model->tenant_id) && app(TenantContext::class)->current() !== null) {
                $model->tenant_id = app(TenantContext::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $location_id
 * @property string $name
 * @property string $type
 * @property int $price_minor
 * @property int $joining_fee_minor
 * @property int|null $duration_days
 * @property int|null $pack_credits
 */
class MembershipPlan extends Model
{
    use BelongsToTenant, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_rules' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

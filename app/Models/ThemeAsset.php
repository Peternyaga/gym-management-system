<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThemeAsset extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $guarded = [];
}

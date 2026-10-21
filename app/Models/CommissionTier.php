<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'rate_bps', 'is_default'])]
class CommissionTier extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['city_id', 'name', 'areas', 'fee_kobo', 'eta_label', 'is_active'])]
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['areas' => 'array', 'is_active' => 'boolean'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** The active zone whose `areas` list contains this area name, if any. */
    public static function matching(?int $cityId, string $area): ?self
    {
        if ($cityId === null) {
            return null;
        }

        return static::where('city_id', $cityId)
            ->where('is_active', true)
            ->whereJsonContains('areas', $area)
            ->first();
    }
}

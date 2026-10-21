<?php

namespace App\Models;

use App\Enums\Condition;
use App\Enums\ListingStatus;
use App\Enums\VerificationStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// status/verification_status/published_at are here too: only ever set from controller-built
// arrays or validated() data (never raw request input), so mass assignment is safe.
#[Fillable([
    'seller_profile_id', 'product_model_id', 'inventory_location_id', 'city_id', 'title', 'variant', 'condition',
    'battery_health', 'imei', 'description', 'price_kobo', 'was_price_kobo', 'quantity', 'warranty_months',
    'warranty_note', 'open_to_swap', 'status', 'verification_status', 'published_at',
])]
#[Hidden(['imei', 'imei_hash'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'condition' => Condition::class,
            'status' => ListingStatus::class,
            'verification_status' => VerificationStatus::class,
            'variant' => 'array',
            'imei' => 'encrypted',
            'open_to_swap' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function isLive(): bool
    {
        return $this->status === ListingStatus::Live && $this->quantity > 0;
    }
}

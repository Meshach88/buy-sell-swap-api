<?php

namespace App\Models;

use App\Enums\KycStatus;
use Database\Factories\SellerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'city_id', 'commission_tier_id', 'shop_name', 'slug', 'area', 'address', 'description', 'logo_path',
    'id_type', 'id_number', 'id_document_path', 'selfie_path',
    'bank_name', 'bank_account_number', 'bank_account_name',
])]
#[Hidden(['id_number', 'id_document_path', 'selfie_path', 'bank_account_number'])]
class SellerProfile extends Model
{
    /** @use HasFactory<SellerProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kyc_status' => KycStatus::class,
            'is_platform' => 'boolean',
            'id_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'kyc_submitted_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
            'bank_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function commissionTier(): BelongsTo
    {
        return $this->belongsTo(CommissionTier::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function isApproved(): bool
    {
        return $this->kyc_status === KycStatus::Approved;
    }
}

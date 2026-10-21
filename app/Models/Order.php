<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The payment/escrow envelope for a checkout. A multi-seller cart produces one
 * Order plus one SellerOrder per seller — the parent Order only ever tracks
 * pending_payment -> paid -> completed/cancelled/refunded; each SellerOrder
 * carries the real per-seller fulfilment pipeline (see SellerOrder).
 *
 * Soft-deleted: a financial record, so ->delete() must never actually destroy
 * it (or cascade-destroy its SellerOrders/OrderItems/Commissions/Payments).
 */
#[Fillable([
    'number', 'buyer_id', 'city_id', 'delivery_zone_id', 'promotion_id', 'delivery_method',
    'delivery_name', 'delivery_phone', 'delivery_street', 'delivery_area',
    'subtotal_kobo', 'delivery_fee_kobo', 'discount_kobo', 'total_kobo',
    'status', 'placed_at', 'paid_at', 'completed_at', 'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function nextNumber(): string
    {
        return 'BSS-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
}

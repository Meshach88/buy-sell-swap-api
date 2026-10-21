<?php

namespace App\Models;

use App\Enums\EscrowStatus;
use App\Enums\SellerOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One seller's share of a multi-seller Order — the real fulfilment unit:
 * new -> accepted -> packed -> with_rider -> delivered, then the buyer
 * separately confirms, which releases escrow_status to released. */
#[Fillable([
    'order_id', 'seller_profile_id', 'settlement_line_id', 'status', 'escrow_status',
    'subtotal_kobo', 'delivery_fee_kobo', 'commission_kobo', 'net_kobo', 'handover_code',
    'accepted_at', 'packed_at', 'handed_over_at', 'delivered_at', 'buyer_confirmed_at', 'released_at',
])]
class SellerOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SellerOrderStatus::class,
            'escrow_status' => EscrowStatus::class,
            'accepted_at' => 'datetime',
            'packed_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'delivered_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

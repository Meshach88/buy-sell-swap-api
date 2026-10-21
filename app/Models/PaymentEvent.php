<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every Paystack webhook call is stored here first (unique on provider+event_id,
 * Paystack's own event id when present) before anything acts on it, so retried
 * deliveries are a safe no-op rather than double-processing a payment. */
#[Fillable(['payment_id', 'provider', 'event', 'event_id', 'reference', 'payload', 'processed_at'])]
class PaymentEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

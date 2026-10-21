<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'path', 'kind', 'label', 'sort_order'])]
class ListingImage extends Model
{
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}

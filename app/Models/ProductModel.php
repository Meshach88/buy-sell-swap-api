<?php

namespace App\Models;

use Database\Factories\ProductModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'brand', 'name', 'slug', 'specs', 'variant_options', 'status', 'requested_by'])]
class ProductModel extends Model
{
    /** @use HasFactory<ProductModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['specs' => 'array', 'variant_options' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}

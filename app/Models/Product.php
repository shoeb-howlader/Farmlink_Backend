<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'short_description',
        'price',
        'stock',
        'low_stock_threshold',
        'image_url',
        'image_path',
        'image_thumbnail_path',
        'description',
        'usage_instructions',
        'video_url',
        'is_active',
        'is_featured',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Get the variants for this product.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the default variant.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * Get the gallery images for this product.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Get the primary gallery image.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Get customer reviews for this product.
     *
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    /**
     * Get the specifications for this product.
     *
     * @return HasMany<ProductSpec, $this>
     */
    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class)->orderBy('sort_order');
    }

    /**
     * Get the documents for this product.
     *
     * @return HasMany<ProductDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class)->latest();
    }

    /**
     * Get average rating (rounded to 1 decimal place).
     */
    public function getRatingAvgAttribute(): ?float
    {
        $avg = $this->reviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    /**
     * Get total review count.
     */
    public function getRatingCountAttribute(): int
    {
        return (int) $this->reviews()->count();
    }

    /**
     * Get star rating distribution counts [5 => N, 4 => N, 3 => N, 2 => N, 1 => N].
     *
     * @return array<int, int>
     */
    public function getRatingDistributionAttribute(): object
    {
        $counts = ProductReview::query()
            ->where('product_id', $this->id)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->all();

        return (object) [
            '5' => (int) ($counts[5] ?? 0),
            '4' => (int) ($counts[4] ?? 0),
            '3' => (int) ($counts[3] ?? 0),
            '2' => (int) ($counts[2] ?? 0),
            '1' => (int) ($counts[1] ?? 0),
        ];
    }

    /**
     * Get minimum price among variants (or product price if no variants).
     */
    public function getMinPriceAttribute(): float
    {
        $min = $this->variants()->min('price');

        return $min !== null ? (float) $min : (float) $this->price;
    }

    /**
     * Get maximum price among variants (or product price if no variants).
     */
    public function getMaxPriceAttribute(): float
    {
        $max = $this->variants()->max('price');

        return $max !== null ? (float) $max : (float) $this->price;
    }

    /**
     * Determine if this product has multiple size/weight variants.
     */
    public function getHasMultipleVariantsAttribute(): bool
    {
        return $this->variants()->count() > 1;
    }

    /**
     * Get derived total stock from product variants.
     */
    public function getStockAttribute(): int
    {
        if ($this->relationLoaded('variants')) {
            if ($this->variants->isNotEmpty()) {
                return (int) $this->variants->sum('stock');
            }
        } elseif ($this->variants()->exists()) {
            return (int) $this->variants()->sum('stock');
        }

        return (int) ($this->attributes['stock'] ?? 0);
    }

    /**
     * Determine if product has any available stock in any variant.
     */
    public function getIsInStockAttribute(): bool
    {
        if ($this->relationLoaded('variants')) {
            if ($this->variants->isNotEmpty()) {
                return $this->variants->contains(fn ($v) => (int) $v->stock > 0);
            }
        } elseif ($this->variants()->exists()) {
            return $this->variants()->where('stock', '>', 0)->exists();
        }

        return (int) ($this->attributes['stock'] ?? 0) > 0;
    }

    /**
     * Synchronize product aggregate price and stock from its variants.
     */
    public function syncAggregateStockAndPrice(): void
    {
        $variants = $this->variants()->get();
        if ($variants->isEmpty()) {
            return;
        }

        $default = $variants->firstWhere('is_default', true) ?? $variants->first();
        $totalStock = $variants->sum('stock');

        $this->updateQuietly([
            'price' => $default->price,
            'stock' => $totalStock,
        ]);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $res = parent::decrement($column, $amount, $extra);
        if ($column === 'stock') {
            $defaultVar = $this->defaultVariant ?? $this->variants()->first();
            if ($defaultVar) {
                $defaultVar->decrement('stock', $amount);
            }
        }

        return $res;
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $res = parent::increment($column, $amount, $extra);
        if ($column === 'stock') {
            $defaultVar = $this->defaultVariant ?? $this->variants()->first();
            if ($defaultVar) {
                $defaultVar->increment('stock', $amount);
            }
        }

        return $res;
    }

    /**
     * Get the order items containing this product.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the stock adjustments for this product.
     *
     * @return HasMany<ProductStockAdjustment, $this>
     */
    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(ProductStockAdjustment::class);
    }

    /**
     * Get the prescription items referencing this product.
     *
     * @return HasMany<PrescriptionItem, $this>
     */
    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'variant_label',
        'sku',
        'price',
        'compare_at_price',
        'sale_starts_at',
        'sale_ends_at',
        'stock',
        'low_stock_threshold',
        'is_default',
    ];

    protected $appends = [
        'is_on_sale',
        'discount_percentage',
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
            'compare_at_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Determine if this variant is currently on promotional sale.
     * Derived purely from pricing and date parameters.
     */
    public function getIsOnSaleAttribute(): bool
    {
        if ($this->compare_at_price === null) {
            return false;
        }

        if ((float) $this->compare_at_price <= (float) $this->price) {
            return false;
        }

        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount percentage if currently on sale.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (! $this->is_on_sale || ! $this->compare_at_price || (float) $this->compare_at_price <= 0) {
            return null;
        }

        $diff = (float) $this->compare_at_price - (float) $this->price;
        return (int) round(($diff / (float) $this->compare_at_price) * 100);
    }

    /**
     * Get the parent product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get order items associated with this variant.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

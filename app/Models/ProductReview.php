<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Boot model events for automatic cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(function (ProductReview $review) {
            \Illuminate\Support\Facades\Cache::forget('product_detail_' . $review->product_id);
            try {
                \Illuminate\Support\Facades\Cache::increment('product_reviews_v_' . $review->product_id);
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Cache::put('product_reviews_v_' . $review->product_id, time(), 86400 * 30);
            }
        });
        static::deleted(function (ProductReview $review) {
            \Illuminate\Support\Facades\Cache::forget('product_detail_' . $review->product_id);
            try {
                \Illuminate\Support\Facades\Cache::increment('product_reviews_v_' . $review->product_id);
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Cache::put('product_reviews_v_' . $review->product_id, time(), 86400 * 30);
            }
        });
    }

    /**
     * Get the reviewed product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the author user (farmer).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related delivered order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

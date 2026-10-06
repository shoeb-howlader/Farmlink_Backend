<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Taxonomy extends Model
{
    protected $fillable = [
        'type',
        'slug',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Boot model events for automatic cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    /**
     * Flush cached taxonomy datasets.
     */
    public static function clearCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('taxonomies.active_grouped');

        $types = [
            'farm_type',
            'culture_type',
            'farming_system',
            'water_source',
            'facility',
            'product_category',
            'specialty_tag',
        ];
        foreach ($types as $type) {
            \Illuminate\Support\Facades\Cache::forget("taxonomies.by_type.{$type}");
        }
    }

    /**
     * Get active taxonomies grouped by type with caching.
     */
    public static function getActiveGroupedCached(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('taxonomies.active_grouped', 86400, function () {
            $all = static::active()->get();
            $grouped = [];
            foreach ($all as $item) {
                $grouped[$item->type][] = [
                    'id' => $item->id,
                    'slug' => $item->slug,
                    'name' => $item->name,
                    'description' => $item->description,
                    'sort_order' => $item->sort_order,
                ];
            }
            return $grouped;
        });
    }

    /**
     * Get active taxonomies for a specific type with caching.
     */
    public static function getByTypeCached(string $type): array
    {
        return \Illuminate\Support\Facades\Cache::remember("taxonomies.by_type.{$type}", 86400, function () use ($type) {
            return static::active()->byType($type)->get()->toArray();
        });
    }

    /**
     * Scope query to a specific taxonomy type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope query to active taxonomies ordered by sort_order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

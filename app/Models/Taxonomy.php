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
        'name_bn',
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
        \Illuminate\Support\Facades\Cache::forget('taxonomies.lookup_map');

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
                    'name_bn' => $item->name_bn,
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

    /**
     * Get lookup map of all active taxonomies with Bangla translations.
     */
    public static function getLookupMapCached(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('taxonomies.lookup_map', 86400, function () {
            $all = static::where('is_active', true)->whereNotNull('name_bn')->get();
            $map = [];
            foreach ($all as $item) {
                $bn = $item->name_bn;
                $map[$item->type][strtolower(trim($item->name))] = $bn;
                $map[$item->type][strtolower(trim($item->slug))] = $bn;
                $map['global'][strtolower(trim($item->name))] = $bn;
                $map['global'][strtolower(trim($item->slug))] = $bn;
            }
            return $map;
        });
    }

    /**
     * Resolve a taxonomy value to its Bangla translation dynamically with fallback.
     */
    public static function resolveBn(string $type, ?string $val): ?string
    {
        if ($val === null || trim($val) === '') {
            return null;
        }

        $k = strtolower(trim($val));
        $map = static::getLookupMapCached();

        if (isset($map[$type][$k])) {
            return $map[$type][$k];
        }
        if (isset($map['global'][$k])) {
            return $map['global'][$k];
        }

        // Canonical fallbacks for standard terms
        $fallbacks = [
            'farm_type' => [
                'own' => 'নিজস্ব জমি / ঘের',
                'own land' => 'নিজস্ব জমি / ঘের',
                'leased' => 'ইজারা / লিজকৃত ঘের',
                'family' => 'পারিবারিক পৈতৃক ঘের',
                'partnership' => 'যৌথ অংশীদারিত্বের ঘের',
                'other' => 'অন্যান্য ধরন',
            ],
            'culture_type' => [
                'bagda' => 'বাগদা চিংড়ি (Black Tiger)',
                'golda' => 'গলদা চিংড়ি (Freshwater Prawn)',
                'tilapia' => 'তেলাপিয়া (Tilapia)',
                'pangas' => 'পাঙ্গাস (Pangas)',
                'carp' => 'কার্প জাতীয় মাছ (Rui/Katla/Carp)',
                'shrimp' => 'বাগদা ও উপকূলীয় চিংড়ি',
                'prawn' => 'গলদা চিংড়ি',
                'fish' => 'সাদা মাছ / কার্প',
                'crab' => 'কাঁকড়া',
                'mixed' => 'মিশ্র মাছ ও চিংড়ি চাষ',
            ],
            'farming_system' => [
                'extensive' => 'সনাতন / ঐতিহ্যবাহী পদ্ধতি (Extensive)',
                'improved extensive' => 'উন্নত সনাতন পদ্ধতি (Improved Extensive)',
                'improved_extensive' => 'উন্নত সনাতন পদ্ধতি (Improved Extensive)',
                'semi-intensive' => 'আধা-নিবিড় পদ্ধতি (Semi-intensive)',
                'semi_intensive' => 'আধা-নিবিড় পদ্ধতি (Semi-intensive)',
                'intensive' => 'নিবিড় এয়ারেটেড পদ্ধতি (Intensive)',
                'traditional' => 'ঐতিহ্যবাহী উপকূলীয় ঘের',
            ],
            'water_source' => [
                'river' => 'নদী / প্রাকৃতিক জলাশয়',
                'canal' => 'ক্যানেল / সরকারি স্লুইস খাল',
                'deep tubewell' => 'গভীর নলকূপ (Deep Tubewell)',
                'deep_tubewell' => 'গভীর নলকূপ (Deep Tubewell)',
                'rainfed' => 'বৃষ্টির পানি / নিজস্ব পুকুর',
                'pond/groundwater' => 'পুকুর ও ভূগর্ভস্থ পানি',
                'tidal creek' => 'জোয়ার-ভাটার খাঁড়ি',
            ],
            'facility' => [
                'electric' => 'বিদ্যুৎ সংযোগ',
                'generator' => 'জেনারেটর',
                'solar' => 'সৌরবিদ্যুৎ সিস্টেম',
                'pump' => 'পানি তোলার পাম্প',
                'aerator' => 'এয়ারেটর / প্যাডেল হুইল',
                'feeding equip.' => 'খাদ্য প্রয়োগ সরঞ্জাম',
                'feeding equipment' => 'খাদ্য প্রয়োগ সরঞ্জাম',
                'feeding_equip' => 'খাদ্য প্রয়োগ সরঞ্জাম',
                'storage' => 'ফিড ও সরঞ্জাম গুদাম',
                'net/harvest' => 'জাল ও আহরণ সরঞ্জাম',
                'net_harvest' => 'জাল ও আহরণ সরঞ্জাম',
                'fencing' => 'সুরক্ষা বেড়া ও নেট বেষ্টনী',
            ],
        ];

        return $fallbacks[$type][$k] ?? null;
    }
}

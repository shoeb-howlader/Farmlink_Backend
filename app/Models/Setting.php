<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected const CACHE_KEY = 'app_settings_all_dict';

    /**
     * In-memory cache for the current PHP execution lifecycle.
     *
     * @var array<string, string|null>|null
     */
    protected static ?array $memoized = null;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            static::flushCache();
        });
        static::deleted(function () {
            static::flushCache();
        });
    }

    /**
     * Clear all cached settings.
     */
    public static function flushCache(): void
    {
        static::$memoized = null;
        try {
            Cache::forget(self::CACHE_KEY);
            $groups = ['general', 'delivery', 'notifications', 'system', 'fraud'];
            foreach ($groups as $grp) {
                Cache::forget("app_settings_group_{$grp}");
            }
        } catch (\Throwable $e) {
            // cache driver fallback
        }
    }

    /**
     * Retrieve all settings as a key-value dictionary with caching and request memoization.
     *
     * @return array<string, string|null>
     */
    public static function allCached(): array
    {
        if (static::$memoized !== null) {
            return static::$memoized;
        }

        try {
            static::$memoized = Cache::remember(self::CACHE_KEY, 86400, function () {
                return static::pluck('value', 'key')->all();
            });
        } catch (\Throwable $e) {
            static::$memoized = static::pluck('value', 'key')->all();
        }

        return static::$memoized;
    }

    /**
     * Get a setting value by key with optional fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (! array_key_exists($key, $all) || $all[$key] === null) {
            return $default;
        }

        return $all[$key];
    }

    /**
     * Set a setting value and log the audit change.
     */
    public static function set(string $key, mixed $value, string $group = 'general', ?int $userId = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $oldValue = $setting->exists ? $setting->value : null;

        if (is_bool($value)) {
            $setting->value = $value ? '1' : '0';
        } else {
            $setting->value = $value !== null ? (string) $value : null;
        }
        $setting->group = $group;
        $setting->save();

        static::flushCache();

        if ($oldValue !== $setting->value) {
            ActivityLog::log(
                'setting.updated',
                $setting,
                [
                    'key' => $key,
                    'old_value' => $oldValue,
                    'new_value' => $setting->value,
                    'group' => $group,
                ],
                $userId
            );
        }

        return $setting;
    }

    /**
     * Get all settings in a group as key-value associative array.
     *
     * @return array<string, string|null>
     */
    public static function getGroup(string $group): array
    {
        $groupCacheKey = "app_settings_group_{$group}";
        try {
            return Cache::remember($groupCacheKey, 86400, function () use ($group) {
                return static::where('group', $group)
                    ->pluck('value', 'key')
                    ->toArray();
            });
        } catch (\Throwable $e) {
            return static::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        }
    }

    /**
     * Batch save settings for a group with audit logging.
     *
     * @param array<string, mixed> $settings
     */
    public static function setMany(array $settings, string $group = 'general', ?int $userId = null): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value, $group, $userId);
        }
    }
}

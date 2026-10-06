<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Http\Requests\Api\V1\UploadImageRequest;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends ApiController
{
    /**
     * Get settings, optionally filtered by group.
     */
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');

        if ($group) {
            $settings = Setting::getGroup($group);
            return $this->successResponse([
                'group' => $group,
                'settings' => $settings,
            ]);
        }

        $all = Setting::all();
        $grouped = [];
        foreach ($all as $item) {
            $grouped[$item->group][$item->key] = $item->value;
        }

        return $this->successResponse([
            'settings' => $grouped,
            'recent_logs' => ActivityLog::where('action', 'setting.updated')
                ->with('actor')
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    /**
     * Update settings with audit trail logging.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['nullable', 'string', 'in:general,delivery,notifications,system,fraud'],
            'settings' => ['required', 'array'],
            'settings.site_name' => ['nullable', 'string', 'max:255'],
            'settings.site_logo_url' => ['nullable', 'string', 'max:500'],
            'settings.contact_phone' => ['nullable', 'string', 'max:50'],
            'settings.contact_email' => ['nullable', 'email', 'max:100'],
            'settings.currency_symbol' => ['nullable', 'string', 'max:10'],
            'settings.currency_code' => ['nullable', 'string', 'max:10'],
            'settings.business_hours' => ['nullable', 'string', 'max:500'],
            'settings.flat_delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'settings.free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'settings.fraud_detection_enabled' => ['nullable', 'boolean'],
            'settings.bulk_cod_threshold' => ['nullable', 'numeric', 'min:0'],
            'settings.cod_max_doorstep_refusals' => ['nullable', 'integer', 'min:0'],
            'settings.advance_delivery_fee_enabled' => ['nullable', 'boolean'],
            'settings.advance_delivery_fee_threshold' => ['nullable', 'numeric', 'min:0'],
            'settings.advance_delivery_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'settings.auto_confirm_on_phone_verified' => ['nullable', 'boolean'],
            'settings.ip_geolocation_enabled' => ['nullable', 'boolean'],
        ]);

        $group = $validated['group'] ?? 'general';
        $userId = $request->user()?->id;

        foreach ($validated['settings'] as $key => $value) {
            // Determine appropriate group if not specified
            $itemGroup = $group;
            if (in_array($key, ['flat_delivery_fee', 'free_delivery_threshold'])) {
                $itemGroup = 'delivery';
            } elseif (str_starts_with($key, 'fraud_') || str_starts_with($key, 'bulk_cod_') || str_starts_with($key, 'cod_max_') || str_starts_with($key, 'advance_delivery_') || str_starts_with($key, 'auto_confirm_') || str_starts_with($key, 'ip_geo')) {
                $itemGroup = 'fraud';
            }

            Setting::set($key, $value, $itemGroup, $userId);
        }

        return $this->successResponse([
            'message' => 'Settings updated successfully',
            'settings' => Setting::getGroup($group),
        ], 'Settings updated successfully');
    }

    /**
     * Upload an organization logo and store in settings.
     */
    public function uploadLogo(UploadImageRequest $request, ImageUploadService $uploader): JsonResponse
    {
        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'branding/logos', 200);

        $oldLogo = Setting::get('site_logo_url');
        Setting::set('site_logo_url', $result['url'], 'general', $request->user()?->id);

        ActivityLog::log(
            'setting.updated',
            null,
            [
                'key' => 'site_logo_url',
                'old_value' => $oldLogo,
                'new_value' => $result['url'],
                'group' => 'general',
            ],
            $request->user()
        );

        return $this->successResponse([
            'logo_url' => $result['url'],
            'thumbnail_url' => $result['thumbnail_url'],
            'settings' => Setting::getGroup('general'),
        ], 'Logo uploaded successfully');
    }

    /**
     * Remove the current organization logo.
     */
    public function removeLogo(Request $request): JsonResponse
    {
        $oldLogo = Setting::get('site_logo_url');
        Setting::set('site_logo_url', null, 'general', $request->user()?->id);

        ActivityLog::log(
            'setting.updated',
            null,
            [
                'key' => 'site_logo_url',
                'old_value' => $oldLogo,
                'new_value' => null,
                'group' => 'general',
            ],
            $request->user()
        );

        return $this->successResponse([
            'logo_url' => null,
            'settings' => Setting::getGroup('general'),
        ], 'Logo removed successfully');
    }

    /**
     * Public endpoint to fetch active operational settings (e.g. for storefront & checkout).
     */
    public function publicSettings(): JsonResponse
    {
        return $this->successResponse([
            'site_name' => Setting::get('site_name', 'FarmLink'),
            'site_logo_url' => Setting::get('site_logo_url'),
            'contact_phone' => Setting::get('contact_phone', '+880 1700-000000'),
            'contact_email' => Setting::get('contact_email', 'support@farmlink.com.bd'),
            'currency_symbol' => Setting::get('currency_symbol', '৳'),
            'currency_code' => Setting::get('currency_code', 'BDT'),
            'business_hours' => Setting::get('business_hours', 'Sat - Thu: 8:00 AM - 6:00 PM'),
            'flat_delivery_fee' => (float) Setting::get('flat_delivery_fee', 50.00),
            'free_delivery_threshold' => Setting::get('free_delivery_threshold') !== null
                ? (float) Setting::get('free_delivery_threshold')
                : null,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}

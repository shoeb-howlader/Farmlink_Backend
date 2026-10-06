<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OrderFraudScoringService
{
    /**
     * Resolve IP Geolocation (City, Region, Country, ISP).
     * Non-blocking, cached for 24 hours per IP.
     *
     * @return array{country: string, city: ?string, region: ?string, isp: ?string}
     */
    public function resolveIpLocation(string $ip, ?Request $request = null): array
    {
        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'country' => 'BD',
                'city' => 'Localhost',
                'region' => 'Local Network',
                'isp' => 'Internal / Dev',
            ];
        }

        return Cache::remember("ip_geo_v1_{$ip}", 86400, function () use ($ip, $request) {
            $country = 'BD';
            $city = null;
            $region = null;
            $isp = null;

            if ($request) {
                if ($request->header('CF-IPCountry')) {
                    $country = strtoupper(substr((string) $request->header('CF-IPCountry'), 0, 2));
                }
                if ($request->header('CF-IPCity')) {
                    $city = (string) $request->header('CF-IPCity');
                }
            }

            $ipGeoEnabled = Setting::get('ip_geolocation_enabled', '1') == '1';
            if ($ipGeoEnabled) {
                try {
                    $response = Http::timeout(2)
                        ->retry(1, 100)
                        ->get("http://ip-api.com/json/{$ip}?fields=status,countryCode,regionName,city,isp,org");

                    if ($response->successful()) {
                        $data = $response->json();
                        if (($data['status'] ?? '') === 'success') {
                            $country = $data['countryCode'] ?? $country;
                            $city = $data['city'] ?? $city;
                            $region = $data['regionName'] ?? $region;
                            $isp = $data['isp'] ?? $data['org'] ?? $isp;
                        }
                    }
                } catch (\Throwable $e) {
                    // Gracefully fallback to defaults if external lookup is unreachable
                }
            }

            return [
                'country' => $country ?: 'BD',
                'city' => $city,
                'region' => $region,
                'isp' => $isp,
            ];
        });
    }

    /**
     * Evaluate risk score and telemetry flags for an incoming order.
     *
     * @param  array<string, mixed>  $orderData
     * @return array<string, mixed>
     */
    public function evaluate(User $user, array $orderData, Request $request): array
    {
        $ip = $request->ip() ?: '127.0.0.1';
        $geo = $this->resolveIpLocation($ip, $request);

        // Check if global fraud scoring is enabled in Admin Settings
        $fraudDetectionEnabled = Setting::get('fraud_detection_enabled', '1') == '1';
        if (! $fraudDetectionEnabled) {
            return [
                'score' => 0,
                'level' => 'low',
                'is_flagged' => false,
                'flag_reasons' => [],
                'verification_status' => 'unverified',
                'ip_country' => $geo['country'],
                'ip_city' => $geo['city'],
                'ip_region' => $geo['region'],
                'ip_isp' => $geo['isp'],
                'allow_cod' => ! $user->cod_blocked,
                'require_advance_fee' => false,
                'advance_fee_amount' => 0.00,
            ];
        }

        $score = 0;
        $reasons = [];

        $total = (float) ($orderData['total'] ?? 0);
        $paymentMode = $orderData['payment_mode'] ?? 'cod';

        // Dynamic thresholds from settings
        $bulkCodThreshold = (float) Setting::get('bulk_cod_threshold', '10000');
        $maxRefusalsThreshold = (int) Setting::get('cod_max_doorstep_refusals', '2');
        $advanceFeeEnabled = Setting::get('advance_delivery_fee_enabled', '1') == '1';
        $advanceFeeThreshold = (float) Setting::get('advance_delivery_fee_threshold', '5000');
        $advanceFeeAmount = (float) Setting::get('advance_delivery_fee_amount', '150');

        // 1. Account Blacklist or COD Block Check
        if ($user->is_blacklisted) {
            $score += 100;
            $reasons[] = 'user_is_blacklisted';
        }

        if ($user->cod_blocked && $paymentMode === 'cod') {
            $score += 80;
            $reasons[] = 'user_cod_privileges_blocked';
        }

        // 2. Doorstep Return / Refusal History
        $refusalCount = $user->refused_cod_orders_count;
        if ($refusalCount >= $maxRefusalsThreshold) {
            $score += 50;
            $reasons[] = 'multiple_prior_cod_refusals';
        } elseif ($refusalCount >= 1) {
            $score += 25;
            $reasons[] = 'prior_cod_refusal_on_record';
        }

        // 3. Brand New Account High-Value COD Order
        $isBrandNewAccount = $user->created_at ? $user->created_at->diffInHours(now()) < 48 : true;
        if ($isBrandNewAccount && $paymentMode === 'cod') {
            if ($total >= $bulkCodThreshold) {
                $score += 35;
                $reasons[] = 'new_account_bulk_cod_over_threshold';
            } elseif ($total >= ($bulkCodThreshold * 0.5)) {
                $score += 20;
                $reasons[] = 'new_account_high_value_cod';
            }
        }

        // 4. IP Velocity & Multi-Account Correlation
        if (! in_array($ip, ['127.0.0.1', '::1'], true)) {
            $distinctUsersOnIp = Order::where('ip_address', $ip)
                ->where('created_at', '>=', now()->subHours(24))
                ->distinct('user_id')
                ->count('user_id');

            if ($distinctUsersOnIp >= 3) {
                $score += 30;
                $reasons[] = 'multiple_accounts_on_same_ip';
            }

            $recentOrdersFromIp = Order::where('ip_address', $ip)
                ->where('created_at', '>=', now()->subHours(2))
                ->count();

            if ($recentOrdersFromIp >= 5) {
                $score += 25;
                $reasons[] = 'high_order_frequency_from_ip';
            }
        }

        // 5. Foreign IP detection
        if ($geo['country'] !== 'BD') {
            $score += 35;
            $reasons[] = "foreign_ip_origin_{$geo['country']}";
        }

        // 6. Recipient Phone Linked to Past Cancellation/Refusal
        $recipientPhone = $orderData['recipient_phone'] ?? null;
        if ($recipientPhone && $recipientPhone !== $user->phone) {
            $phoneHasPriorRefusals = Order::where('recipient_phone', $recipientPhone)
                ->where('status', 'cancelled')
                ->where(function ($q) {
                    $q->where('notes', 'LIKE', '%refused%')
                        ->orWhere('notes', 'LIKE', '%rejected%')
                        ->orWhere('notes', 'LIKE', '%return%');
                })
                ->exists();

            if ($phoneHasPriorRefusals) {
                $score += 40;
                $reasons[] = 'recipient_phone_linked_to_prior_doorstep_return';
            }
        }

        // 7. Loyal Farmer Trust Discount
        $deliveredCount = $user->delivered_orders_count;
        if ($deliveredCount >= 3 && $refusalCount === 0) {
            $score = max(0, $score - 30);
        }

        // Bound final score between 0 and 100
        $finalScore = max(0, min(100, $score));

        $riskLevel = match (true) {
            $finalScore >= 60 => 'high',
            $finalScore >= 25 => 'medium',
            default => 'low',
        };

        $isFlagged = $finalScore >= 25;
        $verificationStatus = ($finalScore < 25 && $deliveredCount >= 3) ? 'auto_trusted' : 'unverified';

        // Advance Delivery Fee Requirement:
        // Triggered if advance fee is enabled AND order is COD AND (total >= threshold OR risk is high/medium)
        $requireAdvanceFee = $advanceFeeEnabled && ($paymentMode === 'cod') && ($total >= $advanceFeeThreshold || $finalScore >= 35);

        return [
            'score' => $finalScore,
            'level' => $riskLevel,
            'is_flagged' => $isFlagged,
            'flag_reasons' => $reasons,
            'verification_status' => $verificationStatus,
            'ip_country' => $geo['country'],
            'ip_city' => $geo['city'],
            'ip_region' => $geo['region'],
            'ip_isp' => $geo['isp'],
            'allow_cod' => (! $user->cod_blocked && $finalScore < 60),
            'require_advance_fee' => $requireAdvanceFee,
            'advance_fee_amount' => $requireAdvanceFee ? $advanceFeeAmount : 0.00,
        ];
    }

    /**
     * Mark an order as verified by a staff member.
     * If phone verified and auto-confirm is enabled, transitions order from 'pending' to 'confirmed'.
     */
    public function verifyOrder(Order $order, User $staffUser, string $status = 'verified_call'): Order
    {
        $updates = [
            'verification_status' => $status,
            'verified_by' => $staffUser->id,
            'verified_at' => now(),
            'is_flagged' => false,
        ];

        // Automatically change order status from 'pending' to 'confirmed' upon phone verification
        $autoConfirm = Setting::get('auto_confirm_on_phone_verified', '1') == '1';
        if ($status === 'verified_call' && $autoConfirm && $order->status === 'pending') {
            $updates['status'] = 'confirmed';
        }

        $order->update($updates);

        return $order;
    }

    /**
     * Record a successful delivery and update customer trust counter.
     */
    public function recordDeliverySuccess(Order $order): void
    {
        if ($order->user) {
            $order->user->increment('delivered_orders_count');
        }
    }

    /**
     * Record a customer doorstep refusal (RTO) and evaluate COD blocking.
     */
    public function recordDoorstepRefusal(Order $order, string $reason): void
    {
        if ($order->user) {
            $order->user->increment('refused_cod_orders_count');

            $maxRefusals = (int) Setting::get('cod_max_doorstep_refusals', '2');
            if ($order->user->refused_cod_orders_count >= $maxRefusals) {
                $order->user->update([
                    'cod_blocked' => true,
                    'blacklist_reason' => "Auto-blocked: {$order->user->refused_cod_orders_count} COD delivery refusals at doorstep",
                ]);
            }
        }

        $orderUpdates = [
            'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . "Doorstep refusal: {$reason}"),
        ];
        if (! in_array($order->status, ['returned', 'cancelled'])) {
            $orderUpdates['status'] = 'returned';
        }
        $order->update($orderUpdates);
    }
}

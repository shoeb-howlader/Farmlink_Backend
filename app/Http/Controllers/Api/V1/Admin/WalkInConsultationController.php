<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use App\Models\VisitTestResult;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalkInConsultationController extends ApiController
{
    /**
     * Record an in-store walk-in consultation or diagnostic lab test for a farmer.
     * Generates a completed service request, clinical/consultant record, test results,
     * optional prescription, triggers SMS notification, and formats cart line items for POS.
     */
    public function store(Request $request): JsonResponse
    {
        $staff = $request->user();

        if (! $staff->hasAnyRole(['admin', 'data_entry_operator', 'consultant', 'veterinary_doctor'])) {
            return $this->errorResponse('Unauthorized to record walk-in store consultations', 403);
        }

        $validated = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:users,id'],
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'type' => ['required', 'string', 'in:consultant,vet'],
            'findings' => ['nullable', 'string', 'max:3000'],
            'treatment' => ['nullable', 'string', 'max:3000'],
            'recommendation' => ['nullable', 'string', 'max:3000'],
            'next_follow_up' => ['nullable', 'date', 'after_or_equal:today'],
            'test_results' => ['nullable', 'array'],
            'test_results.*.parameter' => ['required', 'string', 'max:100'],
            'test_results.*.value' => ['required', 'string', 'max:50'],
            'test_results.*.unit' => ['nullable', 'string', 'max:30'],
            'test_results.*.reference_range' => ['nullable', 'string', 'max:50'],
            'test_results.*.flag' => ['nullable', 'string', 'in:normal,high,low,abnormal'],
            'prescription_items' => ['nullable', 'array'],
            'prescription_items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'prescription_items.*.medicine_name' => ['required', 'string', 'max:255'],
            'prescription_items.*.dosage' => ['nullable', 'string', 'max:150'],
            'prescription_items.*.frequency' => ['nullable', 'string', 'max:100'],
            'prescription_items.*.duration' => ['nullable', 'string', 'max:100'],
            'prescription_items.*.instructions' => ['nullable', 'string', 'max:500'],
            'prescription_items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'test_fee_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'test_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'send_sms' => ['nullable', 'boolean'],
        ]);

        $farmer = User::findOrFail($validated['farmer_id']);

        if ($farmer->is_blacklisted) {
            throw ValidationException::withMessages([
                'farmer_id' => ['Cannot provide consultation: Farmer account is blacklisted (' . ($farmer->blacklist_reason ?? 'Administrative ban') . ').'],
            ]);
        }

        if ($farmer->consultations_blocked) {
            throw ValidationException::withMessages([
                'farmer_id' => ['Cannot provide consultation: Farmer has store and field consultations restricted.'],
            ]);
        }

        // Resolve Farm:
        $farmerFarms = Farm::where('user_id', $farmer->id)->get();
        $farm = null;

        if (! empty($validated['farm_id'])) {
            $farm = $farmerFarms->firstWhere('id', $validated['farm_id']);
            if (! $farm) {
                throw ValidationException::withMessages([
                    'farm_id' => ['The selected farm does not belong to this farmer.'],
                ]);
            }
        } elseif ($farmerFarms->count() === 1) {
            $farm = $farmerFarms->first();
        } elseif ($farmerFarms->count() > 1) {
            throw ValidationException::withMessages([
                'farm_id' => ['Please select which of the farmer\'s farms this consultation is for.'],
            ]);
        } else {
            // Auto-create a primary default farm if farmer has none yet
            $farmDistrict = $farmer->district ?: 'Satkhira';
            $farm = Farm::create([
                'user_id' => $farmer->id,
                'farm_name' => "{$farmer->name}'s Farm",
                'farm_type' => 'aquaculture',
                'main_culture_type' => 'shrimp_fish_mixed',
                'farming_system' => 'semi_intensive',
                'district' => $farmDistrict,
                'upazila' => 'Sadar',
                'total_area' => 33.00,
                'pond_count' => 1,
            ]);
        }

        $type = $validated['type'];
        $nextFollowUp = ! empty($validated['next_follow_up'])
            ? Carbon::parse($validated['next_follow_up'])->toDateString()
            : null;

        $findings = trim($validated['findings'] ?? '');
        $treatment = trim($validated['treatment'] ?? '');
        $recommendation = trim($validated['recommendation'] ?? '');

        // Execute DB Transaction
        $result = DB::transaction(function () use ($staff, $farmer, $farm, $type, $findings, $treatment, $recommendation, $nextFollowUp, $validated) {
            // 1. Create Completed Service Request with source_channel 'walk_in'
            $serviceRequest = ServiceRequest::create([
                'farm_id' => $farm->id,
                'farmer_id' => $farmer->id,
                'type' => $type,
                'description' => "In-store walk-in consultation & diagnostic test conducted by {$staff->name}",
                'urgency' => 'normal',
                'source_channel' => 'walk_in',
                'status' => 'completed',
                'completed_at' => now(),
                'assigned_to' => $staff->id,
                'assigned_at' => now(),
            ]);

            // 2. Create Clinical or Consultant Record
            if ($type === 'vet') {
                $record = VetRecord::create([
                    'farm_id' => $farm->id,
                    'vet_id' => $staff->id,
                    'visit_date' => now()->toDateString(),
                    'findings' => $findings ?: 'In-store walk-in examination conducted at depot.',
                    'treatment' => $treatment ?: ($recommendation ?: 'Standard preventive and supportive measures advised.'),
                    'medicine_given' => ! empty($validated['prescription_items'])
                        ? implode(', ', array_column($validated['prescription_items'], 'medicine_name'))
                        : null,
                    'next_follow_up' => $nextFollowUp,
                ]);

                $serviceRequest->update([
                    'fulfilled_record_type' => VetRecord::class,
                    'fulfilled_record_id' => $record->id,
                ]);
            } else {
                $record = ConsultantRecord::create([
                    'farm_id' => $farm->id,
                    'consultant_id' => $staff->id,
                    'visit_date' => now()->toDateString(),
                    'recommendation' => $recommendation ?: ($findings . ($treatment ? "\n" . $treatment : '') ?: 'Water quality and pond management advisory provided at depot.'),
                    'next_follow_up' => $nextFollowUp,
                ]);

                $serviceRequest->update([
                    'fulfilled_record_type' => ConsultantRecord::class,
                    'fulfilled_record_id' => $record->id,
                ]);
            }

            // 3. Create Visit Test Results
            $testResultsCreated = [];
            if (! empty($validated['test_results'])) {
                foreach ($validated['test_results'] as $tr) {
                    $testRecord = $record->testResults()->create([
                        'parameter' => $tr['parameter'],
                        'value' => $tr['value'],
                        'unit' => $tr['unit'] ?? null,
                        'reference_range' => $tr['reference_range'] ?? null,
                        'flag' => $tr['flag'] ?? 'normal',
                    ]);
                    $testResultsCreated[] = $testRecord;
                }
            }

            // 4. Create Prescription & Items
            $prescription = null;
            if (! empty($validated['prescription_items'])) {
                $prescription = $record->prescription()->create();

                foreach ($validated['prescription_items'] as $item) {
                    $prescription->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'medicine_name' => $item['medicine_name'],
                        'dosage' => $item['dosage'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'instructions' => $item['instructions'] ?? null,
                        'type' => $type === 'vet' ? 'medicine' : 'practice_change',
                    ]);
                }
            }

            // 5. Activity Log
            ActivityLog::log(
                'walk_in.consultation_recorded',
                $record,
                [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'farmer_id' => $farmer->id,
                    'farmer_name' => $farmer->name,
                    'farm_id' => $farm->id,
                    'farm_name' => $farm->farm_name,
                    'type' => $type,
                    'test_count' => count($testResultsCreated),
                    'prescribed_items_count' => count($validated['prescription_items'] ?? []),
                    'has_follow_up' => (bool) $nextFollowUp,
                ],
                $staff
            );

            // 6. Admin Notification
            AdminNotification::notify(
                'service_request.walk_in_completed',
                "Walk-in Visit Completed: {$farm->farm_name}",
                "Staff {$staff->name} recorded a walk-in {$type} consultation & lab test for {$farmer->name}.",
                [
                    'service_request_id' => $serviceRequest->id,
                    'record_id' => $record->id,
                    'record_type' => $type,
                    'farmer_id' => $farmer->id,
                    'farm_id' => $farm->id,
                ],
                $farmer->id
            );

            return [
                'service_request' => $serviceRequest,
                'record' => $record,
                'prescription' => $prescription,
                'test_results' => $testResultsCreated,
            ];
        });

        // 7. Dispatch SMS Notification to Farmer
        $smsSent = false;
        $shouldSendSms = $request->boolean('send_sms', true);

        if ($shouldSendSms && $farmer->phone) {
            try {
                $smsService = app(SmsService::class);
                $abnormalTests = collect($validated['test_results'] ?? [])->filter(fn ($t) => in_array($t['flag'] ?? '', ['high', 'low', 'abnormal']));

                $testSummary = '';
                if ($abnormalTests->isNotEmpty()) {
                    $testSummary = ' টেস্ট: ' . $abnormalTests->map(fn ($t) => "{$t['parameter']} {$t['value']}{$t['unit']} ({$t['flag']})")->implode(', ');
                } elseif (! empty($validated['test_results'])) {
                    $testSummary = ' টেস্ট: সকল প্যারামিটার স্বাভাবিক।';
                }

                $adviceExcerpt = Str::limit($findings ?: ($recommendation ?: $treatment), 45, '...');

                $smsText = "ফার্মলিংক: আপনার ওয়াক-ইন পরামর্শ ও টেস্ট সম্পন্ন হয়েছে।{$testSummary} পরামর্শ: {$adviceExcerpt}";
                if ($nextFollowUp) {
                    $smsText .= " পরবর্তী ফলো-আপ: {$nextFollowUp}।";
                }
                $smsText .= " হটলাইন: 01711223344";

                $smsSent = $smsService->sendMessage($farmer->phone, $smsText);
            } catch (\Throwable $e) {
                Log::warning('Walk-in consultation SMS dispatch failed: ' . $e->getMessage(), [
                    'farmer_id' => $farmer->id,
                    'phone' => $farmer->phone,
                ]);
            }
        }

        // 8. Assemble Ready-to-Cart Items for POS
        $cartItems = [];

        // Add test fee product if requested
        if (! empty($validated['test_fee_product_id']) || (! empty($validated['test_fee_amount']) && (float) $validated['test_fee_amount'] > 0)) {
            $feeProduct = null;
            if (! empty($validated['test_fee_product_id'])) {
                $feeProduct = Product::find($validated['test_fee_product_id']);
            }
            if (! $feeProduct) {
                $feeProduct = Product::where('category', 'Services')->first()
                    ?: Product::firstOrCreate(
                        ['name' => 'Water Quality Test (Lab / Field)'],
                        [
                            'name_bn' => 'পুকুর ও ঘেরের পানি পরীক্ষা',
                            'category' => 'Services',
                            'category_bn' => 'ল্যাব টেস্ট ও সেবা',
                            'price' => (float) ($validated['test_fee_amount'] ?? 100),
                            'stock' => 999999,
                            'is_active' => true,
                        ]
                    );
            }

            if ($feeProduct) {
                $cartItems[] = [
                    'product' => $feeProduct,
                    'quantity' => 1,
                    'overridePrice' => isset($validated['test_fee_amount']) && $validated['test_fee_amount'] !== null
                        ? (float) $validated['test_fee_amount']
                        : (float) $feeProduct->price,
                    'note' => 'In-store diagnostic test service fee',
                ];
            }
        }

        // Add prescribed physical store products
        if (! empty($validated['prescription_items'])) {
            $productIds = collect($validated['prescription_items'])->pluck('product_id')->filter()->unique()->all();
            if (! empty($productIds)) {
                $productsMap = Product::with('variants')->whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($validated['prescription_items'] as $item) {
                    $pid = $item['product_id'] ?? null;
                    if ($pid && isset($productsMap[$pid])) {
                        $prod = $productsMap[$pid];
                        $cartItems[] = [
                            'product' => $prod,
                            'quantity' => (int) ($item['quantity'] ?? 1),
                            'overridePrice' => null,
                            'dosage_instructions' => trim(($item['dosage'] ?? '') . ' ' . ($item['instructions'] ?? '')),
                        ];
                    }
                }
            }
        }

        $prescription = $result['prescription'];
        $pdfUrl = $prescription ? url("/api/v1/prescriptions/{$prescription->id}/pdf") : null;

        return $this->successResponse([
            'record_id' => $result['record']->id,
            'record_type' => $type,
            'service_request_id' => $result['service_request']->id,
            'prescription_id' => $prescription?->id,
            'pdf_url' => $pdfUrl,
            'test_results_count' => count($result['test_results']),
            'cart_items' => $cartItems,
            'sms_sent' => $smsSent,
            'farm' => [
                'id' => $farm->id,
                'name' => $farm->farm_name,
                'district' => $farm->district,
            ],
            'farmer' => [
                'id' => $farmer->id,
                'name' => $farmer->name,
                'phone' => $farmer->phone,
            ],
            'next_follow_up' => $nextFollowUp,
        ], 'Walk-in consultation & test recorded successfully. Items prepared for POS cart.', 201);
    }

    /**
     * Get recent walk-in consultation & test history for a specific farmer.
     * Allows staff at the POS counter to review historical test parameters and past prescriptions.
     */
    public function history(Request $request, int $farmerId): JsonResponse
    {
        $farmer = User::findOrFail($farmerId);

        $farms = Farm::where('user_id', $farmer->id)->pluck('id');

        $vetRecords = VetRecord::with(['vet:id,name', 'testResults', 'prescription.items.product', 'farm:id,farm_name'])
            ->whereIn('farm_id', $farms)
            ->latest('visit_date')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'vet',
                'visit_date' => $r->visit_date?->toDateString(),
                'practitioner' => $r->vet?->name,
                'findings' => $r->findings,
                'treatment' => $r->treatment,
                'farm_name' => $r->farm?->farm_name,
                'next_follow_up' => $r->next_follow_up?->toDateString(),
                'test_results' => $r->testResults,
                'prescription_id' => $r->prescription?->id,
                'prescription_pdf_url' => $r->prescription ? url("/api/v1/prescriptions/{$r->prescription->id}/pdf") : null,
                'items' => $r->prescription?->items->map(fn ($i) => [
                    'medicine_name' => $i->medicine_name,
                    'dosage' => $i->dosage,
                    'instructions' => $i->instructions,
                    'product_name' => $i->product?->name,
                ]),
            ]);

        $consultantRecords = ConsultantRecord::with(['consultant:id,name', 'testResults', 'prescription.items.product', 'farm:id,farm_name'])
            ->whereIn('farm_id', $farms)
            ->latest('visit_date')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'consultant',
                'visit_date' => $r->visit_date?->toDateString(),
                'practitioner' => $r->consultant?->name,
                'recommendation' => $r->recommendation,
                'farm_name' => $r->farm?->farm_name,
                'next_follow_up' => $r->next_follow_up?->toDateString(),
                'test_results' => $r->testResults,
                'prescription_id' => $r->prescription?->id,
                'prescription_pdf_url' => $r->prescription ? url("/api/v1/prescriptions/{$r->prescription->id}/pdf") : null,
                'items' => $r->prescription?->items->map(fn ($i) => [
                    'medicine_name' => $i->medicine_name,
                    'dosage' => $i->dosage,
                    'instructions' => $i->instructions,
                    'product_name' => $i->product?->name,
                ]),
            ]);

        $combined = $vetRecords->concat($consultantRecords)
            ->sortByDesc('visit_date')
            ->values()
            ->take(8);

        return $this->successResponse($combined, 'Walk-in history retrieved successfully');
    }
}

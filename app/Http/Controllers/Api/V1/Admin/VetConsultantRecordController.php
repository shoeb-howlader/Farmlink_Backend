<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ConsultantRecord;
use App\Models\Order;
use App\Models\VetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VetConsultantRecordController extends ApiController
{
    /**
     * Display a paginated listing of vet and consultant records.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $type = $request->query('type', 'all'); // 'all', 'vet', 'consultant'
        $search = trim($request->query('search', ''));
        $district = $request->query('district');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $practitionerId = $request->query('practitioner_id');

        // Apply filters to query builder closures
        $applyVetFilters = function ($q) use ($search, $district, $dateFrom, $dateTo, $practitionerId) {
            if ($practitionerId) {
                $q->where('vet_id', $practitionerId);
            }
            if ($district && $district !== 'all') {
                $q->whereHas('farm', fn ($fq) => $fq->where('district', $district));
            }
            if ($dateFrom) {
                $q->whereDate('visit_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('visit_date', '<=', $dateTo);
            }
            if ($search !== '') {
                $q->where(function ($sub) use ($search) {
                    $sub->where('findings', 'like', "%{$search}%")
                        ->orWhere('treatment', 'like', "%{$search}%")
                        ->orWhere('medicine_given', 'like', "%{$search}%")
                        ->orWhereHas('farm', function ($fq) use ($search) {
                            $fq->where('farm_name', 'like', "%{$search}%")
                                ->orWhereHas('farmer', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                        })
                        ->orWhereHas('vet', fn ($vq) => $vq->where('name', 'like', "%{$search}%"));
                });
            }
        };

        $applyConsultantFilters = function ($q) use ($search, $district, $dateFrom, $dateTo, $practitionerId) {
            if ($practitionerId) {
                $q->where('consultant_id', $practitionerId);
            }
            if ($district && $district !== 'all') {
                $q->whereHas('farm', fn ($fq) => $fq->where('district', $district));
            }
            if ($dateFrom) {
                $q->whereDate('visit_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('visit_date', '<=', $dateTo);
            }
            if ($search !== '') {
                $q->where(function ($sub) use ($search) {
                    $sub->where('recommendation', 'like', "%{$search}%")
                        ->orWhereHas('farm', function ($fq) use ($search) {
                            $fq->where('farm_name', 'like', "%{$search}%")
                                ->orWhereHas('farmer', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                        })
                        ->orWhereHas('consultant', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            }
        };

        if ($type === 'vet') {
            $vetQuery = VetRecord::with([
                'farm.farmer', 'vet', 'prescription.items.product', 'parentRecord',
                'followUpRecords', 'testResults', 'photos',
                'fulfilledServiceRequest:id,fulfilled_record_type,fulfilled_record_id',
            ]);
            $applyVetFilters($vetQuery);
            $paginated = $vetQuery->latest('visit_date')->paginate($perPage);

            $slice = $paginated->getCollection()->map(fn (VetRecord $vr) => $this->mapVetRecord($vr))->values();
            $total = $paginated->total();
            $currentPage = $paginated->currentPage();
            $lastPage = $paginated->lastPage();
        } elseif ($type === 'consultant') {
            $consultantQuery = ConsultantRecord::with([
                'farm.farmer', 'consultant', 'prescription.items.product', 'parentRecord',
                'followUpRecords', 'testResults', 'photos',
                'fulfilledServiceRequest:id,fulfilled_record_type,fulfilled_record_id',
            ]);
            $applyConsultantFilters($consultantQuery);
            $paginated = $consultantQuery->latest('visit_date')->paginate($perPage);

            $slice = $paginated->getCollection()->map(fn (ConsultantRecord $cr) => $this->mapConsultantRecord($cr))->values();
            $total = $paginated->total();
            $currentPage = $paginated->currentPage();
            $lastPage = $paginated->lastPage();
        } else {
            // Combined DB-level pagination using union
            $vetIdQuery = VetRecord::selectRaw("id, 'vet' as record_type, visit_date, created_at");
            $applyVetFilters($vetIdQuery);

            $consultantIdQuery = ConsultantRecord::selectRaw("id, 'consultant' as record_type, visit_date, created_at");
            $applyConsultantFilters($consultantIdQuery);

            $unionQuery = $vetIdQuery->unionAll($consultantIdQuery);

            $paginatedIds = DB::query()
                ->fromSub($unionQuery, 'combined_records')
                ->orderByDesc('visit_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage);

            $total = $paginatedIds->total();
            $currentPage = $paginatedIds->currentPage();
            $lastPage = $paginatedIds->lastPage();

            $vetIds = [];
            $consultantIds = [];
            foreach ($paginatedIds->items() as $item) {
                if ($item->record_type === 'vet') {
                    $vetIds[] = $item->id;
                } else {
                    $consultantIds[] = $item->id;
                }
            }

            $vetsMap = empty($vetIds) ? collect() : VetRecord::with([
                'farm.farmer', 'vet', 'prescription.items.product', 'parentRecord',
                'followUpRecords', 'testResults', 'photos',
                'fulfilledServiceRequest:id,fulfilled_record_type,fulfilled_record_id',
            ])->whereIn('id', $vetIds)->get()->keyBy('id');

            $consultantsMap = empty($consultantIds) ? collect() : ConsultantRecord::with([
                'farm.farmer', 'consultant', 'prescription.items.product', 'parentRecord',
                'followUpRecords', 'testResults', 'photos',
                'fulfilledServiceRequest:id,fulfilled_record_type,fulfilled_record_id',
            ])->whereIn('id', $consultantIds)->get()->keyBy('id');

            $slice = collect($paginatedIds->items())->map(function ($item) use ($vetsMap, $consultantsMap) {
                if ($item->record_type === 'vet') {
                    $vr = $vetsMap->get($item->id);
                    return $vr ? $this->mapVetRecord($vr) : null;
                } else {
                    $cr = $consultantsMap->get($item->id);
                    return $cr ? $this->mapConsultantRecord($cr) : null;
                }
            })->filter()->values();
        }

        $summary = Cache::remember('admin_vet_consultant_records_summary', 60, function () {
            return [
                'total_vet' => VetRecord::count(),
                'total_consultant' => ConsultantRecord::count(),
                'upcoming_follow_ups' => (int) (VetRecord::where('next_follow_up', '>=', now()->toDateString())->count()
                    + ConsultantRecord::where('next_follow_up', '>=', now()->toDateString())->count()),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Vet and consultant records retrieved successfully',
            'data' => $slice,
            'summary' => $summary,
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Map a VetRecord model to response array.
     */
    protected function mapVetRecord(VetRecord $vr): array
    {
        return [
            'id' => $vr->id,
            'record_type' => 'vet',
            'visit_date' => $vr->visit_date?->toDateString(),
            'next_follow_up' => $vr->next_follow_up?->toDateString(),
            'findings' => $vr->findings,
            'treatment' => $vr->treatment,
            'medicine_given' => $vr->medicine_given,
            'recommendation' => null,
            'parent_record_id' => $vr->parent_record_id,
            'prescription' => $vr->prescription ? [
                'id' => $vr->prescription->id,
                'pdf_url' => url("/api/v1/prescriptions/{$vr->prescription->id}/pdf"),
                'items' => $vr->prescription->items->map(fn ($item) => [
                    'id' => $item->id,
                    'medicine_name' => $item->medicine_name,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'instructions' => $item->instructions,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'price' => $item->product->price,
                        'stock' => $item->product->stock,
                    ] : null,
                ]),
            ] : null,
            'farm' => $vr->farm ? [
                'id' => $vr->farm->id,
                'name' => $vr->farm->farm_name,
                'farm_name' => $vr->farm->farm_name,
                'district' => $vr->farm->district,
                'farmer' => $vr->farm->farmer ? [
                    'id' => $vr->farm->farmer->id,
                    'name' => $vr->farm->farmer->name,
                    'phone' => $vr->farm->farmer->phone,
                ] : null,
            ] : null,
            'practitioner' => $vr->vet ? [
                'id' => $vr->vet->id,
                'name' => $vr->vet->name,
                'role' => 'veterinary_doctor',
            ] : null,
            'test_results' => $vr->testResults->map(fn ($t) => [
                'id' => $t->id,
                'parameter' => $t->parameter,
                'value' => $t->value,
                'unit' => $t->unit,
                'reference_range' => $t->reference_range,
                'flag' => $t->flag,
            ]),
            'photos' => $vr->photos->map(fn ($p) => [
                'id' => $p->id,
                'photo_path' => $p->photo_path,
                'url' => $p->url,
                'thumbnail_url' => $p->thumbnail_url,
                'caption' => $p->caption,
                'sort_order' => $p->sort_order,
            ]),
            'service_request_id' => $vr->fulfilledServiceRequest?->id,
            'created_at' => $vr->created_at?->toISOString(),
        ];
    }

    /**
     * Map a ConsultantRecord model to response array.
     */
    protected function mapConsultantRecord(ConsultantRecord $cr): array
    {
        return [
            'id' => $cr->id,
            'record_type' => 'consultant',
            'visit_date' => $cr->visit_date?->toDateString(),
            'next_follow_up' => $cr->next_follow_up?->toDateString(),
            'findings' => null,
            'treatment' => null,
            'medicine_given' => null,
            'recommendation' => $cr->recommendation,
            'parent_record_id' => $cr->parent_record_id,
            'prescription' => $cr->prescription ? [
                'id' => $cr->prescription->id,
                'pdf_url' => url("/api/v1/prescriptions/{$cr->prescription->id}/pdf"),
                'items' => $cr->prescription->items->map(fn ($item) => [
                    'id' => $item->id,
                    'medicine_name' => $item->medicine_name,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'instructions' => $item->instructions,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'price' => $item->product->price,
                        'stock' => $item->product->stock,
                    ] : null,
                ]),
            ] : null,
            'farm' => $cr->farm ? [
                'id' => $cr->farm->id,
                'name' => $cr->farm->farm_name,
                'farm_name' => $cr->farm->farm_name,
                'district' => $cr->farm->district,
                'farmer' => $cr->farm->farmer ? [
                    'id' => $cr->farm->farmer->id,
                    'name' => $cr->farm->farmer->name,
                    'phone' => $cr->farm->farmer->phone,
                ] : null,
            ] : null,
            'practitioner' => $cr->consultant ? [
                'id' => $cr->consultant->id,
                'name' => $cr->consultant->name,
                'role' => 'consultant',
            ] : null,
            'test_results' => $cr->testResults->map(fn ($t) => [
                'id' => $t->id,
                'parameter' => $t->parameter,
                'value' => $t->value,
                'unit' => $t->unit,
                'reference_range' => $t->reference_range,
                'flag' => $t->flag,
            ]),
            'photos' => $cr->photos->map(fn ($p) => [
                'id' => $p->id,
                'photo_path' => $p->photo_path,
                'url' => $p->url,
                'thumbnail_url' => $p->thumbnail_url,
                'caption' => $p->caption,
                'sort_order' => $p->sort_order,
            ]),
            'service_request_id' => $cr->fulfilledServiceRequest?->id,
            'created_at' => $cr->created_at?->toISOString(),
        ];
    }

    /**
     * Directly update or reschedule the follow-up date for a vet or consultant record.
     */
    public function updateFollowUp(Request $request, string $type, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $validated = $request->validate([
            'next_follow_up' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $record = $type === 'vet' ? VetRecord::find($id) : ConsultantRecord::find($id);

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        $oldDate = $record->next_follow_up ? $record->next_follow_up->toDateString() : null;
        $newDate = \Carbon\Carbon::parse($validated['next_follow_up'])->toDateString();
        $reason = trim($validated['reason'] ?? 'Directly updated by administrator.');

        if (! $record->original_follow_up_date && $oldDate) {
            $record->original_follow_up_date = $oldDate;
        }

        $record->next_follow_up = $newDate;
        $record->rescheduled_reason = $reason;
        $record->rescheduled_at = now();
        $record->rescheduled_by = $request->user()->id;
        $record->save();

        Cache::forget('admin_vet_consultant_records_summary');

        \App\Models\ActivityLog::log(
            'record.follow_up_rescheduled',
            $record,
            [
                'record_type' => $type,
                'record_id' => $record->id,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'reason' => $reason,
                'rescheduled_by_admin' => $request->user()->name,
            ],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Follow-up date successfully updated.',
            'data' => [
                'record_id' => $record->id,
                'record_type' => $type,
                'next_follow_up' => $newDate,
                'original_follow_up_date' => $record->original_follow_up_date ? \Carbon\Carbon::parse($record->original_follow_up_date)->toDateString() : null,
                'rescheduled_reason' => $reason,
                'rescheduled_at' => $record->rescheduled_at?->toISOString(),
            ],
        ]);
    }
}

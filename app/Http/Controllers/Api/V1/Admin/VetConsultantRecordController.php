<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ConsultantRecord;
use App\Models\Order;
use App\Models\VetRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $records = collect();

        $practitionerId = $request->query('practitioner_id');

        if ($type === 'all' || $type === 'vet') {
            $vetQuery = VetRecord::with(['farm.farmer', 'vet', 'prescription.items.product', 'parentRecord', 'followUpRecords', 'testResults', 'photos']);

            if ($practitionerId) {
                $vetQuery->where('vet_id', $practitionerId);
            }

            if ($district && $district !== 'all') {
                $vetQuery->whereHas('farm', function ($q) use ($district) {
                    $q->where('district', $district);
                });
            }

            if ($dateFrom) {
                $vetQuery->whereDate('visit_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $vetQuery->whereDate('visit_date', '<=', $dateTo);
            }

            if ($search !== '') {
                $vetQuery->where(function ($q) use ($search) {
                    $q->where('findings', 'like', "%{$search}%")
                        ->orWhere('treatment', 'like', "%{$search}%")
                        ->orWhere('medicine_given', 'like', "%{$search}%")
                        ->orWhereHas('farm', function ($fq) use ($search) {
                            $fq->where('farm_name', 'like', "%{$search}%")
                                ->orWhereHas('farmer', function ($uq) use ($search) {
                                    $uq->where('name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('vet', function ($vq) use ($search) {
                            $vq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $vetRecords = $vetQuery->latest('visit_date')->get()->map(function (VetRecord $vr) {
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
                        'items' => $vr->prescription->items->map(fn($item) => [
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
                    'test_results' => $vr->testResults->map(fn($t) => [
                        'id' => $t->id,
                        'parameter' => $t->parameter,
                        'value' => $t->value,
                        'unit' => $t->unit,
                        'reference_range' => $t->reference_range,
                        'flag' => $t->flag,
                    ]),
                    'photos' => $vr->photos->map(fn($p) => [
                        'id' => $p->id,
                        'photo_path' => $p->photo_path,
                        'url' => $p->url,
                        'thumbnail_url' => $p->thumbnail_url,
                        'caption' => $p->caption,
                        'sort_order' => $p->sort_order,
                    ]),
                    'created_at' => $vr->created_at?->toISOString(),
                ];
            });

            $records = $records->concat($vetRecords);
        }

        if ($type === 'all' || $type === 'consultant') {
            $consultantQuery = ConsultantRecord::with(['farm.farmer', 'consultant', 'prescription.items.product', 'parentRecord', 'followUpRecords', 'testResults', 'photos']);

            if ($practitionerId) {
                $consultantQuery->where('consultant_id', $practitionerId);
            }

            if ($district && $district !== 'all') {
                $consultantQuery->whereHas('farm', function ($q) use ($district) {
                    $q->where('district', $district);
                });
            }

            if ($dateFrom) {
                $consultantQuery->whereDate('visit_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $consultantQuery->whereDate('visit_date', '<=', $dateTo);
            }

            if ($search !== '') {
                $consultantQuery->where(function ($q) use ($search) {
                    $q->where('recommendation', 'like', "%{$search}%")
                        ->orWhereHas('farm', function ($fq) use ($search) {
                            $fq->where('farm_name', 'like', "%{$search}%")
                                ->orWhereHas('farmer', function ($uq) use ($search) {
                                    $uq->where('name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('consultant', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $consultantRecords = $consultantQuery->latest('visit_date')->get()->map(function (ConsultantRecord $cr) {
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
                        'items' => $cr->prescription->items->map(fn($item) => [
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
                    'test_results' => $cr->testResults->map(fn($t) => [
                        'id' => $t->id,
                        'parameter' => $t->parameter,
                        'value' => $t->value,
                        'unit' => $t->unit,
                        'reference_range' => $t->reference_range,
                        'flag' => $t->flag,
                    ]),
                    'photos' => $cr->photos->map(fn($p) => [
                        'id' => $p->id,
                        'photo_path' => $p->photo_path,
                        'url' => $p->url,
                        'thumbnail_url' => $p->thumbnail_url,
                        'caption' => $p->caption,
                        'sort_order' => $p->sort_order,
                    ]),
                    'created_at' => $cr->created_at?->toISOString(),
                ];
            });

            $records = $records->concat($consultantRecords);
        }

        // Sort descending by visit_date / created_at
        $sorted = $records->sortByDesc(fn ($item) => $item['visit_date'] ?? $item['created_at'])->values();

        $page = (int) $request->query('page', 1);
        $total = $sorted->count();
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $totalVet = VetRecord::count();
        $totalConsultant = ConsultantRecord::count();
        $upcomingFollowUps = VetRecord::where('next_follow_up', '>=', now()->toDateString())->count()
            + ConsultantRecord::where('next_follow_up', '>=', now()->toDateString())->count();

        return response()->json([
            'success' => true,
            'message' => 'Vet and consultant records retrieved successfully',
            'data' => $slice,
            'summary' => [
                'total_vet' => $totalVet,
                'total_consultant' => $totalConsultant,
                'upcoming_follow_ups' => $upcomingFollowUps,
            ],
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpController extends ApiController
{
    /**
     * Display a listing of farms where follow-up date has passed with no newer record logged since.
     */
    public function overdue(Request $request): JsonResponse
    {
        abort_if(! $request->user()->hasRole('admin'), 403, 'Unauthorized.');

        $today = now()->toDateString();

        $farms = Farm::query()
            ->where(function ($query) use ($today) {
                // Overdue VetRecord with no newer VetRecord logged since
                $query->whereHas('vetRecords', function ($q) use ($today) {
                    $q->whereNotNull('next_follow_up')
                        ->where('next_follow_up', '<', $today)
                        ->whereNotExists(function ($sub) {
                            $sub->selectRaw(1)
                                ->from('vet_records as vr2')
                                ->whereColumn('vr2.farm_id', 'vet_records.farm_id')
                                ->where(function ($condition) {
                                    $condition->whereColumn('vr2.visit_date', '>', 'vet_records.visit_date')
                                        ->orWhere(function ($tie) {
                                            $tie->whereColumn('vr2.visit_date', '=', 'vet_records.visit_date')
                                                ->whereColumn('vr2.id', '>', 'vet_records.id');
                                        });
                                });
                        });
                })
                // Overdue ConsultantRecord with no newer ConsultantRecord logged since
                ->orWhereHas('consultantRecords', function ($q) use ($today) {
                    $q->whereNotNull('next_follow_up')
                        ->where('next_follow_up', '<', $today)
                        ->whereNotExists(function ($sub) {
                            $sub->selectRaw(1)
                                ->from('consultant_records as cr2')
                                ->whereColumn('cr2.farm_id', 'consultant_records.farm_id')
                                ->where(function ($condition) {
                                    $condition->whereColumn('cr2.visit_date', '>', 'consultant_records.visit_date')
                                        ->orWhere(function ($tie) {
                                            $tie->whereColumn('cr2.visit_date', '=', 'consultant_records.visit_date')
                                                ->whereColumn('cr2.id', '>', 'consultant_records.id');
                                        });
                                });
                        });
                });
            })
            ->with('user')
            ->get();

        return $this->successResponse(
            FarmResource::collection($farms),
            'Overdue follow-up farms retrieved successfully'
        );
    }
}

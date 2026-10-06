<?php

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\VetRecord;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill ServiceRequest for any unlinked VetRecord
        $vetRecords = VetRecord::with('farm')->get();
        foreach ($vetRecords as $rec) {
            $existing = ServiceRequest::where('fulfilled_record_type', VetRecord::class)
                ->where('fulfilled_record_id', $rec->id)
                ->first();

            if (! $existing) {
                $farmerId = $rec->farm?->user_id;
                if (! $farmerId) {
                    $farm = Farm::find($rec->farm_id);
                    $farmerId = $farm?->user_id;
                }

                if ($farmerId) {
                    $visitDate = $rec->visit_date ? Carbon::parse($rec->visit_date) : $rec->created_at;
                    $createdAt = $visitDate->copy()->subHours(24);

                    ServiceRequest::create([
                        'farm_id' => $rec->farm_id,
                        'farmer_id' => $farmerId,
                        'type' => 'vet',
                        'description' => $rec->findings ?: 'Veterinary clinical examination and pond treatment protocol.',
                        'urgency' => 'normal',
                        'status' => 'completed',
                        'source_channel' => 'self_service',
                        'assigned_to' => $rec->vet_id,
                        'assigned_at' => $visitDate->copy()->subHours(12),
                        'completed_at' => $visitDate,
                        'fulfilled_record_type' => VetRecord::class,
                        'fulfilled_record_id' => $rec->id,
                        'created_at' => $createdAt,
                        'updated_at' => $visitDate,
                    ]);
                }
            }
        }

        // 2. Backfill ServiceRequest for any unlinked ConsultantRecord
        $consultantRecords = ConsultantRecord::with('farm')->get();
        foreach ($consultantRecords as $rec) {
            $existing = ServiceRequest::where('fulfilled_record_type', ConsultantRecord::class)
                ->where('fulfilled_record_id', $rec->id)
                ->first();

            if (! $existing) {
                $farmerId = $rec->farm?->user_id;
                if (! $farmerId) {
                    $farm = Farm::find($rec->farm_id);
                    $farmerId = $farm?->user_id;
                }

                if ($farmerId) {
                    $visitDate = $rec->visit_date ? Carbon::parse($rec->visit_date) : $rec->created_at;
                    $createdAt = $visitDate->copy()->subHours(24);

                    ServiceRequest::create([
                        'farm_id' => $rec->farm_id,
                        'farmer_id' => $farmerId,
                        'type' => 'consultant',
                        'description' => $rec->recommendation ?: 'Aquaculture pond management and biosecurity advisory consultation.',
                        'urgency' => 'normal',
                        'status' => 'completed',
                        'source_channel' => 'self_service',
                        'assigned_to' => $rec->consultant_id,
                        'assigned_at' => $visitDate->copy()->subHours(12),
                        'completed_at' => $visitDate,
                        'fulfilled_record_type' => ConsultantRecord::class,
                        'fulfilled_record_id' => $rec->id,
                        'created_at' => $createdAt,
                        'updated_at' => $visitDate,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op or clean backfilled requests
    }
};

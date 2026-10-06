<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FarmCycleResource;
use App\Http\Resources\V1\FarmLedgerEntryResource;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\FarmCycle;
use App\Models\FarmLedgerEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FarmLedgerController extends ApiController
{
    /**
     * Categories list with icons and types.
     */
    public static function getCategories(): array
    {
        return [
            // Expenses
            ['name' => 'Feed', 'type' => 'expense', 'icon' => 'i-lucide-wheat', 'color' => 'amber'],
            ['name' => 'Labor', 'type' => 'expense', 'icon' => 'i-lucide-users', 'color' => 'blue'],
            ['name' => 'Fuel', 'type' => 'expense', 'icon' => 'i-lucide-fuel', 'color' => 'orange'],
            ['name' => 'Repair', 'type' => 'expense', 'icon' => 'i-lucide-wrench', 'color' => 'neutral'],
            ['name' => 'Pond Preparation', 'type' => 'expense', 'icon' => 'i-lucide-waves', 'color' => 'cyan'],
            ['name' => 'Electricity', 'type' => 'expense', 'icon' => 'i-lucide-zap', 'color' => 'yellow'],
            ['name' => 'Supplies', 'type' => 'expense', 'icon' => 'i-lucide-package', 'color' => 'indigo'],
            ['name' => 'Veterinary Care', 'type' => 'expense', 'icon' => 'i-lucide-stethoscope', 'color' => 'emerald'],
            ['name' => 'Consultation Fee', 'type' => 'expense', 'icon' => 'i-lucide-lightbulb', 'color' => 'teal'],
            ['name' => 'Other Expense', 'type' => 'expense', 'icon' => 'i-lucide-more-horizontal', 'color' => 'neutral'],

            // Income
            ['name' => 'Harvest Sale', 'type' => 'income', 'icon' => 'i-lucide-banknote', 'color' => 'emerald'],
            ['name' => 'Fingerling Sale', 'type' => 'income', 'icon' => 'i-lucide-fish', 'color' => 'teal'],
            ['name' => 'Government Subsidy', 'type' => 'income', 'icon' => 'i-lucide-landmark', 'color' => 'blue'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'i-lucide-plus-circle', 'color' => 'emerald'],
        ];
    }

    /**
     * Display a listing of the farm ledger entries along with financial summaries.
     */
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to view this farm financial ledger', 403);
        }

        // Lifetime calculation in a single query
        $lifetimeTotals = FarmLedgerEntry::where('farm_id', $farm->id)
            ->active()
            ->selectRaw("
                coalesce(sum(case when entry_type = 'expense' then amount else 0 end), 0) as total_expense,
                coalesce(sum(case when entry_type = 'income' then amount else 0 end), 0) as total_income
            ")
            ->first();

        $lifetimeExpense = (float) ($lifetimeTotals->total_expense ?? 0);
        $lifetimeIncome = (float) ($lifetimeTotals->total_income ?? 0);
        $lifetimeNet = $lifetimeIncome - $lifetimeExpense;

        // Current open cycle calculation (if one exists)
        $currentCycle = $farm->currentCycle;
        $currentCycleSummary = null;
        if ($currentCycle) {
            $cycleTotals = FarmLedgerEntry::where('farm_id', $farm->id)
                ->active()
                ->where(function ($q) use ($currentCycle) {
                    $q->where('cycle_id', $currentCycle->id)
                        ->orWhere(function ($sub) use ($currentCycle) {
                            $sub->where('entry_date', '>=', $currentCycle->start_date);
                            if ($currentCycle->end_date) {
                                $sub->where('entry_date', '<=', $currentCycle->end_date);
                            }
                        });
                })
                ->selectRaw("
                    coalesce(sum(case when entry_type = 'expense' then amount else 0 end), 0) as total_expense,
                    coalesce(sum(case when entry_type = 'income' then amount else 0 end), 0) as total_income
                ")
                ->first();

            $currentCycleExpense = (float) ($cycleTotals->total_expense ?? 0);
            $currentCycleIncome = (float) ($cycleTotals->total_income ?? 0);

            $currentCycleSummary = [
                'cycle' => new FarmCycleResource($currentCycle),
                'total_expense' => $currentCycleExpense,
                'total_income' => $currentCycleIncome,
                'net_profit' => $currentCycleIncome - $currentCycleExpense,
            ];
        }

        // Filtered Query for the Entries List
        $query = FarmLedgerEntry::where('farm_id', $farm->id)
            ->with(['cycle', 'enteredBy', 'order']);

        // Filter: Cycle
        if ($request->filled('cycle_id') && $request->cycle_id !== 'all') {
            if ($request->cycle_id === 'current' && $currentCycle) {
                $query->where(function ($q) use ($currentCycle) {
                    $q->where('cycle_id', $currentCycle->id)
                        ->orWhere(function ($sub) use ($currentCycle) {
                            $sub->where('entry_date', '>=', $currentCycle->start_date);
                            if ($currentCycle->end_date) {
                                $sub->where('entry_date', '<=', $currentCycle->end_date);
                            }
                        });
                });
            } elseif (is_numeric($request->cycle_id)) {
                $targetCycle = FarmCycle::where('farm_id', $farm->id)->find($request->cycle_id);
                if ($targetCycle) {
                    $query->where(function ($q) use ($targetCycle) {
                        $q->where('cycle_id', $targetCycle->id)
                            ->orWhere(function ($sub) use ($targetCycle) {
                                $sub->where('entry_date', '>=', $targetCycle->start_date);
                                if ($targetCycle->end_date) {
                                    $sub->where('entry_date', '<=', $targetCycle->end_date);
                                }
                            });
                    });
                } else {
                    $query->where('cycle_id', $request->cycle_id);
                }
            } elseif ($request->cycle_id === 'unassigned') {
                $query->whereNull('cycle_id');
            }
        }

        // Filter: Entry Type (income | expense)
        if ($request->filled('entry_type') && in_array($request->entry_type, ['income', 'expense'])) {
            $query->where('entry_type', $request->entry_type);
        }

        // Filter: Category
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter: Date Range
        if ($request->filled('start_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('entry_date', '<=', $request->end_date);
        }

        // Filter: Search in note or category
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter: Source
        if ($request->filled('source') && in_array($request->source, ['manual', 'system_order', 'system_service'])) {
            $query->where('source', $request->source);
        }

        // Order by entry_date desc, id desc
        $entries = $query->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        // Monthly Chart Data (Last 6 months) in a single grouped query
        $sixMonthsStart = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $sixMonthsEnd = Carbon::now()->endOfMonth()->toDateString();

        $monthlyAggregates = FarmLedgerEntry::where('farm_id', $farm->id)
            ->active()
            ->whereBetween('entry_date', [$sixMonthsStart, $sixMonthsEnd])
            ->selectRaw("
                substr(cast(entry_date as text), 1, 7) as period,
                coalesce(sum(case when entry_type = 'income' then amount else 0 end), 0) as income,
                coalesce(sum(case when entry_type = 'expense' then amount else 0 end), 0) as expense
            ")
            ->groupByRaw("substr(cast(entry_date as text), 1, 7)")
            ->get()
            ->keyBy('period');

        $chartMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $dt = Carbon::now()->subMonths($i);
            $yearMonth = $dt->format('Y-m');
            $row = $monthlyAggregates->get($yearMonth);
            $mIncome = (float) ($row->income ?? 0);
            $mExpense = (float) ($row->expense ?? 0);

            $chartMonths[] = [
                'period' => $yearMonth,
                'label' => $dt->format('M Y'),
                'income' => $mIncome,
                'expense' => $mExpense,
                'net' => $mIncome - $mExpense,
            ];
        }

        // Cycles breakdown for chart
        $allCycles = $farm->cycles()->get();
        $cycleChart = $allCycles->map(function ($c) use ($farm) {
            $cQuery = FarmLedgerEntry::where('farm_id', $farm->id)
                ->active()
                ->where(function ($q) use ($c) {
                    $q->where('cycle_id', $c->id)
                        ->orWhere(function ($sub) use ($c) {
                            $sub->where('entry_date', '>=', $c->start_date);
                            if ($c->end_date) {
                                $sub->where('entry_date', '<=', $c->end_date);
                            }
                        });
                });

            $cIncome = (float) (clone $cQuery)->where('entry_type', 'income')->sum('amount');
            $cExpense = (float) (clone $cQuery)->where('entry_type', 'expense')->sum('amount');

            return [
                'cycle_id' => $c->id,
                'label' => $c->label,
                'income' => $cIncome,
                'expense' => $cExpense,
                'net' => $cIncome - $cExpense,
                'is_open' => $c->isOpen(),
            ];
        });

        $paginated = FarmLedgerEntryResource::collection($entries)->response()->getData(true);

        return $this->successResponse([
            'summary' => [
                'lifetime' => [
                    'total_expense' => $lifetimeExpense,
                    'total_income' => $lifetimeIncome,
                    'net_profit' => $lifetimeNet,
                ],
                'current_cycle' => $currentCycleSummary,
            ],
            'chart_data' => [
                'monthly' => $chartMonths,
                'by_cycle' => $cycleChart,
            ],
            'cycles' => FarmCycleResource::collection($allCycles),
            'categories' => self::getCategories(),
            'entries' => $paginated['data'],
            'meta' => $paginated['meta'] ?? [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'per_page' => $entries->perPage(),
            ],
        ], 'Farm ledger entries retrieved successfully');
    }

    /**
     * Store a newly created manual ledger entry.
     */
    public function store(Request $request, Farm $farm): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to add ledger entries to this farm', 403);
        }

        $validated = $request->validate([
            'entry_type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'entry_date' => ['required', 'date'],
            'cycle_id' => ['nullable', 'integer', 'exists:farm_cycles,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5MB receipt/slip image
        ]);

        // If cycle_id is provided, verify it belongs to this farm
        if (! empty($validated['cycle_id'])) {
            $cycle = FarmCycle::where('farm_id', $farm->id)->find($validated['cycle_id']);
            if (! $cycle) {
                return $this->errorResponse('The selected cycle does not belong to this farm', 422);
            }
        } else {
            // Auto-detect open cycle for that date if none specified
            $autoCycle = FarmCycle::where('farm_id', $farm->id)
                ->where('start_date', '<=', $validated['entry_date'])
                ->where(function ($q) use ($validated) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $validated['entry_date']);
                })
                ->latest('start_date')
                ->first();
            $validated['cycle_id'] = $autoCycle?->id;
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('farm_ledger_receipts', 'public');
        }

        // Determine entered_by_role
        $role = 'farmer';
        if ($user->hasRole('admin')) {
            $role = 'admin';
        } elseif ($user->hasAnyRole(['data_entry_operator', 'deo'])) {
            $role = 'deo';
        }

        $entry = FarmLedgerEntry::create([
            'farm_id' => $farm->id,
            'cycle_id' => $validated['cycle_id'] ?? null,
            'entry_type' => $validated['entry_type'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'entry_date' => $validated['entry_date'],
            'note' => $validated['note'] ?? null,
            'photo_path' => $photoPath,
            'source' => 'manual',
            'source_reference_id' => null,
            'entered_by' => $user->id,
            'entered_by_role' => $role,
        ]);

        ActivityLog::log(
            'farm_ledger.created',
            $entry,
            [
                'farm_id' => $farm->id,
                'entry_type' => $entry->entry_type,
                'category' => $entry->category,
                'amount' => (float) $entry->amount,
                'entry_date' => $entry->entry_date,
            ],
            $user
        );

        return $this->createdResponse(
            new FarmLedgerEntryResource($entry->load(['cycle', 'enteredBy'])),
            'Ledger entry recorded successfully'
        );
    }

    /**
     * Display a specific ledger entry.
     */
    public function show(Request $request, Farm $farm, FarmLedgerEntry $entry): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to view this entry', 403);
        }

        if ($entry->farm_id !== $farm->id) {
            return $this->errorResponse('Entry does not belong to this farm', 404);
        }

        return $this->successResponse(
            new FarmLedgerEntryResource($entry->load(['cycle', 'enteredBy', 'order'])),
            'Ledger entry retrieved successfully'
        );
    }

    /**
     * Update an existing manual ledger entry.
     */
    public function update(Request $request, Farm $farm, FarmLedgerEntry $entry): JsonResponse
    {
        $user = $request->user();

        if ($entry->farm_id !== $farm->id) {
            return $this->errorResponse('Entry does not belong to this farm', 404);
        }

        // Authorization check via policy
        if ($user->cannot('update', $entry)) {
            if ($entry->source !== 'manual') {
                return $this->errorResponse('System-generated entries cannot be edited', 403);
            }
            if ($entry->isVoided()) {
                return $this->errorResponse('Voided entries cannot be edited', 403);
            }
            return $this->errorResponse('Unauthorized to edit this ledger entry', 403);
        }

        $validated = $request->validate([
            'entry_type' => ['sometimes', Rule::in(['income', 'expense'])],
            'category' => ['sometimes', 'string', 'max:50'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999999.99'],
            'entry_date' => ['sometimes', 'date'],
            'cycle_id' => ['nullable', 'integer', 'exists:farm_cycles,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if (array_key_exists('cycle_id', $validated) && ! empty($validated['cycle_id'])) {
            $cycle = FarmCycle::where('farm_id', $farm->id)->find($validated['cycle_id']);
            if (! $cycle) {
                return $this->errorResponse('The selected cycle does not belong to this farm', 422);
            }
        }

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('farm_ledger_receipts', 'public');
        }

        $beforeValues = $entry->only(['entry_type', 'category', 'amount', 'entry_date', 'note', 'cycle_id']);

        $entry->update($validated);

        $afterValues = $entry->fresh()->only(['entry_type', 'category', 'amount', 'entry_date', 'note', 'cycle_id']);

        // Log audit trail (especially critical for admin dispute resolution)
        ActivityLog::log(
            'farm_ledger.updated',
            $entry,
            [
                'farm_id' => $farm->id,
                'before' => $beforeValues,
                'after' => $afterValues,
                'edited_by_role' => $user->hasRole('admin') ? 'admin' : 'farmer',
            ],
            $user
        );

        return $this->successResponse(
            new FarmLedgerEntryResource($entry->fresh(['cycle', 'enteredBy'])),
            'Ledger entry updated successfully'
        );
    }

    /**
     * Void an existing manual ledger entry.
     */
    public function void(Request $request, Farm $farm, FarmLedgerEntry $entry): JsonResponse
    {
        $user = $request->user();

        if ($entry->farm_id !== $farm->id) {
            return $this->errorResponse('Entry does not belong to this farm', 404);
        }

        if ($user->cannot('void', $entry)) {
            if ($entry->source !== 'manual') {
                return $this->errorResponse('System-generated entries cannot be voided', 403);
            }
            if ($entry->isVoided()) {
                return $this->errorResponse('This entry is already voided', 422);
            }
            return $this->errorResponse('Unauthorized to void this ledger entry', 403);
        }

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $entry->update([
            'voided_at' => now(),
            'void_reason' => $validated['void_reason'],
        ]);

        ActivityLog::log(
            'farm_ledger.voided',
            $entry,
            [
                'farm_id' => $farm->id,
                'amount' => (float) $entry->amount,
                'category' => $entry->category,
                'void_reason' => $validated['void_reason'],
                'voided_by_role' => $user->hasRole('admin') ? 'admin' : ($user->hasAnyRole(['data_entry_operator', 'deo']) ? 'deo' : 'farmer'),
            ],
            $user
        );

        return $this->successResponse(
            new FarmLedgerEntryResource($entry->fresh(['cycle', 'enteredBy'])),
            'Ledger entry voided successfully'
        );
    }
}

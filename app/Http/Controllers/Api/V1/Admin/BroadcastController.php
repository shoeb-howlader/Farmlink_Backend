<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BroadcastController extends ApiController
{
    /**
     * Display a listing of past broadcasts with sent and read counts.
     */
    public function index(Request $request): JsonResponse
    {
        $broadcasts = Broadcast::with('creator')
            ->withCount(['notifications as read_count' => function ($query) {
                $query->whereNotNull('read_at');
            }])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $data = $broadcasts->items();

        return response()->json([
            'success' => true,
            'message' => 'Broadcast history retrieved successfully',
            'data' => collect($data)->map(function (Broadcast $b) {
                return [
                    'id' => $b->id,
                    'title' => $b->title,
                    'message' => $b->message,
                    'target_audience' => $b->target_audience,
                    'target_district' => $b->target_district,
                    'target_role' => $b->target_role,
                    'target_user_ids' => $b->target_user_ids,
                    'sent_count' => (int) $b->sent_count,
                    'read_count' => (int) $b->read_count,
                    'created_by' => $b->created_by,
                    'creator' => $b->creator ? [
                        'id' => $b->creator->id,
                        'name' => $b->creator->name,
                        'email' => $b->creator->email,
                    ] : null,
                    'created_at' => $b->created_at?->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $broadcasts->currentPage(),
                'last_page' => $broadcasts->lastPage(),
                'per_page' => $broadcasts->perPage(),
                'total' => $broadcasts->total(),
            ],
        ]);
    }

    /**
     * Store and fan out a new broadcast notification.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:5'],
            'target_audience' => ['required', 'string', 'in:all_farmers,district_farmers,all_staff,staff_role,manual'],
            'target_district' => ['required_if:target_audience,district_farmers', 'nullable', 'string'],
            'target_role' => ['required_if:target_audience,staff_role', 'nullable', 'string', 'in:veterinary_doctor,consultant,data_entry_operator,admin'],
            'target_user_ids' => ['required_if:target_audience,manual', 'nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Resolve recipients query
        $userQuery = User::query()->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        });

        switch ($validated['target_audience']) {
            case 'all_farmers':
                $userQuery->role('farmer');
                break;

            case 'district_farmers':
                $userQuery->role('farmer')->where('district', $validated['target_district']);
                break;

            case 'all_staff':
                $userQuery->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'data_entry_operator', 'veterinary_doctor', 'consultant']);
                });
                break;

            case 'staff_role':
                $userQuery->role($validated['target_role']);
                break;

            case 'manual':
                $userQuery->whereIn('id', $validated['target_user_ids'] ?? []);
                break;
        }

        $recipientIds = $userQuery->pluck('id')->unique()->values();

        $broadcast = DB::transaction(function () use ($request, $validated, $recipientIds) {
            $broadcast = Broadcast::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'target_audience' => $validated['target_audience'],
                'target_district' => $validated['target_district'] ?? null,
                'target_role' => $validated['target_role'] ?? null,
                'target_user_ids' => $validated['target_user_ids'] ?? null,
                'sent_count' => $recipientIds->count(),
                'created_by' => $request->user()->id,
            ]);

            // Offload recipient fan-out to asynchronous queued job
            \App\Jobs\FanOutBroadcastNotificationsJob::dispatch(
                $broadcast,
                $recipientIds->all(),
                $request->user()->id,
                $request->user()->name
            );

            ActivityLog::log(
                'broadcast.sent',
                $broadcast,
                [
                    'admin_id' => $request->user()->id,
                    'admin_name' => $request->user()->name,
                    'title' => $broadcast->title,
                    'target_audience' => $broadcast->target_audience,
                    'sent_count' => $broadcast->sent_count,
                ]
            );

            return $broadcast;
        });

        return $this->createdResponse([
            'id' => $broadcast->id,
            'title' => $broadcast->title,
            'message' => $broadcast->message,
            'target_audience' => $broadcast->target_audience,
            'sent_count' => $broadcast->sent_count,
            'read_count' => 0,
            'created_at' => $broadcast->created_at?->toISOString(),
        ], 'Broadcast message sent successfully');
    }
}

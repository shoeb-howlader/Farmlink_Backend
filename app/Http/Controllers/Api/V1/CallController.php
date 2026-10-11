<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CallSignalSent;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CallController extends ApiController
{
    /**
     * Generate ephemeral WebRTC STUN/TURN credentials for Coturn BDIX.
     */
    public function getTurnCredentials(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = config('webrtc.coturn.secret', 'farmlink_bdix_turn_secret_2026_x89q4w');
        $host = config('webrtc.coturn.host', '165.99.219.252');
        $port = config('webrtc.coturn.port', 3478);
        $ttl = config('webrtc.coturn.ttl', 86400);

        // Standard WebRTC REST API ephemeral HMAC authentication
        $timestamp = time() + $ttl;
        $username = "{$timestamp}:{$user->id}";
        $password = base64_encode(hash_hmac('sha1', $username, $secret, true));

        return $this->successResponse([
            'iceServers' => [
                [
                    'urls' => [
                        "stun:{$host}:{$port}",
                        'stun:stun.l.google.com:19302',
                    ],
                ],
                [
                    'urls' => [
                        "turn:{$host}:{$port}?transport=udp",
                        "turn:{$host}:{$port}?transport=tcp",
                    ],
                    'username' => $username,
                    'credential' => $password,
                ],
            ],
            'ttl' => $ttl,
            'host' => $host,
        ], 'TURN credentials generated successfully');
    }

    /**
     * Relay WebRTC signaling events (offer, answer, candidate, ring, reject, etc.)
     */
    public function sendSignal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have access to call in this conversation.');
        }

        $validated = $request->validate([
            'signal_type' => ['required', 'string', 'in:initiate,ring,offer,answer,candidate,reject,busy,end'],
            'call_type' => ['required', 'string', 'in:audio,video'],
            'sdp' => ['nullable'],
            'candidate' => ['nullable'],
            'recipient_id' => ['nullable', 'integer'],
        ]);

        $callerRole = $user->roles->first()?->name ?? ($user->role ?? 'farmer');
        $payload = [
            'sender_id' => $user->id,
            'signal_type' => $validated['signal_type'],
            'call_type' => $validated['call_type'],
            'caller' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $callerRole,
                'avatar' => $user->avatar_url ?? null,
            ],
            'recipient_id' => $validated['recipient_id'] ?? null,
            'sdp' => $validated['sdp'] ?? null,
            'candidate' => $validated['candidate'] ?? null,
            'conversation_type' => $conversation->type,
        ];

        \Illuminate\Support\Facades\Log::info("WebRTC sendSignal: conv {$id}, signal {$validated['signal_type']} from user {$user->id}");

        // Broadcast signal via Reverb
        broadcast(new CallSignalSent($conversation->id, $payload));

        return $this->successResponse([
            'signal_sent' => true,
            'signal_type' => $validated['signal_type'],
        ], 'Signal relayed successfully');
    }

    /**
     * Atomically claim an incoming call when multiple Support/DEO staff receive the ring.
     */
    public function claimCall(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('Access denied');
        }

        $callType = $request->input('call_type', 'audio');
        $lockKey = "call_claim_lock_{$id}";
        $claimedKey = "call_claimed_{$id}";

        $lock = Cache::lock($lockKey, 5);

        if ($lock->get()) {
            try {
                $existingClaim = Cache::get($claimedKey);
                if ($existingClaim && (int) $existingClaim['staff_id'] !== (int) $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Call was already answered by another team member.',
                        'claimed_by' => $existingClaim,
                    ], 409);
                }

                $staffRole = $user->roles->first()?->name ?? ($user->role ?? 'staff');
                $claimData = [
                    'staff_id' => $user->id,
                    'name' => $user->name,
                    'role' => $staffRole,
                    'claimed_at' => now()->toIso8601String(),
                ];

                Cache::put($claimedKey, $claimData, 300); // 5 min TTL

                // Broadcast claim event so all other staff screens stop ringing immediately
                broadcast(new CallSignalSent($conversation->id, [
                    'signal_type' => 'claim',
                    'claimed_by' => $claimData,
                    'call_type' => $callType,
                ]));

                return $this->successResponse([
                    'claimed' => true,
                    'claimed_by' => $claimData,
                ], 'Call claimed successfully');
            } finally {
                $lock->release();
            }
        }

        return $this->errorResponse('Could not acquire call lock, please try again.', 429);
    }

    /**
     * End a call and log call duration / status into chat history.
     */
    public function endCall(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('Access denied');
        }

        $validated = $request->validate([
            'call_type' => ['nullable', 'string', 'in:audio,video'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'in:completed,rejected,missed,busy,network_failed'],
        ]);

        $callType = $validated['call_type'] ?? 'audio';
        $durationSeconds = (int) ($validated['duration_seconds'] ?? 0);
        $reason = $validated['reason'] ?? ($durationSeconds > 0 ? 'completed' : 'missed');

        // Clear active claim in cache
        Cache::forget("call_claimed_{$id}");

        // Broadcast call end to other participants
        broadcast(new CallSignalSent($conversation->id, [
            'signal_type' => 'end',
            'call_type' => $callType,
            'duration_seconds' => $durationSeconds,
            'reason' => $reason,
            'ended_by' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]))->toOthers();

        // Format system chat record
        $icon = $callType === 'video' ? '📹' : '📞';
        $typeLabel = $callType === 'video' ? 'Video call' : 'Audio call';

        if ($durationSeconds > 0) {
            $minutes = floor($durationSeconds / 60);
            $seconds = $durationSeconds % 60;
            $durationFormatted = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
            $messageBody = "{$icon} {$typeLabel} ended ({$durationFormatted})";
        } elseif ($reason === 'rejected') {
            $messageBody = "{$icon} {$typeLabel} declined";
        } elseif ($reason === 'busy') {
            $messageBody = "{$icon} {$typeLabel} - Line busy";
        } else {
            $messageBody = "{$icon} Missed {$typeLabel}";
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $messageBody,
            'read_at' => null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        ConversationParticipant::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['last_read_at' => now()]
        );

        broadcast(new MessageSent($message));

        return $this->successResponse([
            'ended' => true,
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 'Call ended successfully');
    }

    /**
     * Check if user is authorized to access the conversation.
     */
    protected function canAccessConversation($user, Conversation $conversation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($conversation->type === 'support' && $user->hasAnyRole(['admin', 'data_entry_operator'])) {
            return true;
        }

        if ($conversation->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($conversation->created_by === $user->id) {
            return true;
        }

        if ($conversation->type === 'practitioner' && $conversation->context_type === 'service_request') {
            $sr = ServiceRequest::find($conversation->context_id);
            if ($sr && ((int) $sr->farmer_id === (int) $user->id || (int) $sr->assigned_to === (int) $user->id)) {
                return true;
            }
        }

        return false;
    }
}

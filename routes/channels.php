<?php

use App\Models\Conversation;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('support.inbox', function ($user) {
    return $user->hasAnyRole(['admin', 'data_entry_operator']);
});

/**
 * Global presence channel for tracking online users in the app.
 * channel: online (presence-online)
 */
Broadcast::channel('online', function ($user) {
    if ($user) {
        $role = $user->roles->first()?->name ?? ($user->role ?? 'farmer');
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator', 'deo']);
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'role' => $role,
            'is_staff' => $isStaff,
        ];
    }
    return false;
});

/**
 * Shared presence channel for the farmer-facing Support Desk identity.
 * channel: support.desk (presence-support.desk)
 */
Broadcast::channel('support.desk', function ($user) {
    if ($user) {
        $role = $user->roles->first()?->name ?? ($user->role ?? 'farmer');
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator', 'deo']);
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'role' => $role,
            'is_staff' => $isStaff,
        ];
    }
    return false;
});

/**
 * Authorize private or presence channel for a specific conversation.
 * channel: chat.{conversationId}
 */
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (! $conversation) {
        return false;
    }

    $canAccess = false;

    // Admins and DEOs can access any support conversation (shared inbox)
    if ($conversation->type === 'support' && $user->hasAnyRole(['admin', 'data_entry_operator'])) {
        $canAccess = true;
    } elseif ($conversation->participants()->where('user_id', $user->id)->exists()) {
        $canAccess = true;
    } elseif ($conversation->type === 'practitioner' && $conversation->context_type === 'service_request') {
        // For practitioner conversations scoped to a service request
        $serviceRequest = ServiceRequest::find($conversation->context_id);
        if ($serviceRequest && (
            (int) $serviceRequest->farmer_id === (int) $user->id ||
            (int) $serviceRequest->assigned_to === (int) $user->id
        )) {
            $canAccess = true;
        }
    } elseif ($user->hasRole('admin')) {
        // System administrators have full oversight
        $canAccess = true;
    }

    if ($canAccess) {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'role' => $user->role,
        ];
    }

    return false;
});

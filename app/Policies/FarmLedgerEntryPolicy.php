<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\FarmLedgerEntry;
use App\Models\User;

class FarmLedgerEntryPolicy
{
    /**
     * Determine whether the user can view ledger entries for the farm.
     */
    public function viewAny(User $user, Farm $farm): bool
    {
        if ($user->hasRole('admin') || $user->hasAnyRole(['data_entry_operator', 'deo'])) {
            return true;
        }

        return $user->id === $farm->user_id;
    }

    /**
     * Determine whether the user can create a ledger entry for the farm.
     */
    public function create(User $user, Farm $farm): bool
    {
        if ($user->hasRole('admin') || $user->hasAnyRole(['data_entry_operator', 'deo'])) {
            return true;
        }

        return $user->id === $farm->user_id;
    }

    /**
     * Determine whether the user can update a ledger entry.
     */
    public function update(User $user, FarmLedgerEntry $entry): bool
    {
        // System-generated entries are read-only
        if ($entry->source !== 'manual') {
            return false;
        }

        // Voided entries cannot be modified
        if ($entry->isVoided()) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        // DEO is append-only: cannot edit existing entries
        if ($user->hasAnyRole(['data_entry_operator', 'deo'])) {
            return false;
        }

        // Farmer can only edit entries they personally created on their own farm
        return $user->id === $entry->entered_by && $user->id === $entry->farm->user_id;
    }

    /**
     * Determine whether the user can void a ledger entry.
     */
    public function void(User $user, FarmLedgerEntry $entry): bool
    {
        // System-generated entries are read-only
        if ($entry->source !== 'manual') {
            return false;
        }

        // Already voided
        if ($entry->isVoided()) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        // DEO can void entries they personally created
        if ($user->hasAnyRole(['data_entry_operator', 'deo'])) {
            return $user->id === $entry->entered_by;
        }

        // Farmer can void entries they personally created on their farm
        return $user->id === $entry->entered_by && $user->id === $entry->farm->user_id;
    }
}

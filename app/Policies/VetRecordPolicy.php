<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;
use App\Models\VetRecord;

class VetRecordPolicy
{
    /**
     * Determine whether the user can view any vet records for the given farm.
     */
    public function viewAny(User $user, Farm $farm): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('farmer')) {
            return $user->id === $farm->user_id;
        }

        if ($user->hasRole('veterinary_doctor')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the specific vet record.
     */
    public function view(User $user, VetRecord $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('farmer')) {
            return $user->id === $record->farm->user_id;
        }

        if ($user->hasRole('veterinary_doctor')) {
            return $user->id === $record->vet_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create vet records.
     */
    public function create(User $user, Farm $farm): bool
    {
        return $user->hasAnyRole(['veterinary_doctor', 'admin']);
    }

    /**
     * Determine whether the user can update the vet record.
     */
    public function update(User $user, VetRecord $record): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('veterinary_doctor') && $user->id === $record->vet_id);
    }

    /**
     * Determine whether the user can delete the vet record.
     */
    public function delete(User $user, VetRecord $record): bool
    {
        return $user->hasRole('admin');
    }
}

<?php

namespace App\Policies;

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\User;

class ConsultantRecordPolicy
{
    /**
     * Determine whether the user can view any consultant records for the given farm.
     */
    public function viewAny(User $user, Farm $farm): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('farmer')) {
            return $user->id === $farm->user_id;
        }

        if ($user->hasRole('consultant')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the specific consultant record.
     */
    public function view(User $user, ConsultantRecord $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('farmer')) {
            return $user->id === $record->farm->user_id;
        }

        if ($user->hasRole('consultant')) {
            return $user->id === $record->consultant_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create consultant records.
     */
    public function create(User $user, Farm $farm): bool
    {
        return $user->hasAnyRole(['consultant', 'admin']);
    }

    /**
     * Determine whether the user can update the consultant record.
     */
    public function update(User $user, ConsultantRecord $record): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('consultant') && $user->id === $record->consultant_id);
    }

    /**
     * Determine whether the user can delete the consultant record.
     */
    public function delete(User $user, ConsultantRecord $record): bool
    {
        return $user->hasRole('admin');
    }
}

<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the admin farm directory.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'data_entry_operator']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Farm $farm): bool
    {
        return $user->id === $farm->user_id
            || $user->hasAnyRole(['admin', 'data_entry_operator', 'veterinary_doctor', 'consultant']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['farmer', 'data_entry_operator', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Farm $farm): bool
    {
        return $user->id === $farm->user_id
            || $user->hasAnyRole(['admin', 'data_entry_operator']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Farm $farm): bool
    {
        return $user->id === $farm->user_id
            || $user->hasRole('admin');
    }
}

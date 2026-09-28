<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkNote;

class WorkNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkNote $workNote): bool
    {
        return $user->id === $workNote->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkNote $workNote): bool
    {
        return $user->id === $workNote->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkNote $workNote): bool
    {
        return $user->id === $workNote->user_id;
    }

    /**
     * Determine whether the user can toggle pin on the model.
     */
    public function togglePin(User $user, WorkNote $workNote): bool
    {
        return $user->id === $workNote->user_id;
    }
}

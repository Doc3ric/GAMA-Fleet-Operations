<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTask;

class WorkTaskPolicy
{
    /**
     * Admins and operators can list tasks (each sees only their own — enforced in the controller).
     * Drivers have no access to the My Work feature.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    /**
     * Admins can view any task; operators can only view their own.
     */
    public function view(User $user, WorkTask $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Admins and operators can create tasks.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    /**
     * Admins can update any task; operators can only update their own.
     */
    public function update(User $user, WorkTask $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Admins can delete any task; operators can only delete their own.
     */
    public function delete(User $user, WorkTask $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Admins can complete any task; operators can only complete their own.
     */
    public function complete(User $user, WorkTask $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }
}

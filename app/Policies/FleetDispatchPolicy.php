<?php

namespace App\Policies;

use App\Models\FleetDispatch;
use App\Models\User;

class FleetDispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing() || $user->isDriver();
    }

    public function view(User $user, FleetDispatch $dispatch): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing() || $user->isDriver();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, FleetDispatch $dispatch): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, ?FleetDispatch $dispatch = null): bool
    {
        return $user->isAdmin();
    }

    public function recordDeparture(User $user, FleetDispatch $dispatch): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isDriver();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }
}

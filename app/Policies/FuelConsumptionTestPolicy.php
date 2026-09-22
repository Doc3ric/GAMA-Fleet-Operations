<?php

namespace App\Policies;

use App\Models\FuelConsumptionTest;
use App\Models\User;

class FuelConsumptionTestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function view(User $user, FuelConsumptionTest $test): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->id === $test->created_by;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, FuelConsumptionTest $test): bool
    {
        return $user->isAdmin() || $user->id === $test->created_by;
    }

    public function delete(User $user, FuelConsumptionTest $test): bool
    {
        return $user->isAdmin() || $user->id === $test->created_by;
    }
}

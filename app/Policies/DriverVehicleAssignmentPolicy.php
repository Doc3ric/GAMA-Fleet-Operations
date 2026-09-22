<?php

namespace App\Policies;

use App\Models\DriverVehicleAssignment;
use App\Models\User;

class DriverVehicleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function view(User $user, DriverVehicleAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->id === $assignment->driver_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, DriverVehicleAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, DriverVehicleAssignment $assignment): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }
}

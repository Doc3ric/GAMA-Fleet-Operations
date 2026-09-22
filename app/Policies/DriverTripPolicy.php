<?php

namespace App\Policies;

use App\Models\DriverTrip;
use App\Models\User;

class DriverTripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function view(User $user, DriverTrip $trip): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->id === $trip->driver_id;
    }

    public function create(User $user): bool
    {
        return $user->isDriver();
    }

    public function end(User $user, DriverTrip $trip): bool
    {
        if (! $trip->isInProgress()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $trip->driver_id;
    }

    public function cancel(User $user, DriverTrip $trip): bool
    {
        if (! $trip->isInProgress()) {
            return false;
        }

        return $user->isAdmin() || $user->id === $trip->driver_id;
    }
}

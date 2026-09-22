<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function view(User $user, Location $location): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->isAdmin();
    }

    public function resolve(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }
}

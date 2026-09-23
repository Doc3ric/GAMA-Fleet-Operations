<?php

namespace App\Policies;

use App\Models\AdvancedItinerary;
use App\Models\User;

class AdvancedItineraryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function view(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin();
    }

    public function recalculate(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }
}

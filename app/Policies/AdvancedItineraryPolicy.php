<?php

namespace App\Policies;

use App\Models\AdvancedItinerary;
use App\Models\User;

class AdvancedItineraryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }

    public function view(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }

    public function update(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }

    public function delete(User $user, ?AdvancedItinerary $itinerary = null): bool
    {
        return $user->isAdmin() || $user->isPurchasing();
    }

    public function recalculate(User $user, AdvancedItinerary $itinerary): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function updateChecklist(User $user, ?AdvancedItinerary $itinerary = null): bool
    {
        return $user->isAdmin() || $user->isPurchasing();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }
}

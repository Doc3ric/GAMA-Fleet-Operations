<?php

namespace App\Policies;

use App\Models\MileageReconciliation;
use App\Models\User;

class MileageReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing() || $user->isDriver();
    }

    public function view(User $user, MileageReconciliation $reconciliation): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing() || $user->isDriver();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, MileageReconciliation $reconciliation): bool
    {
        // Approved records cannot be edited directly
        if ($reconciliation->isApproved()) {
            return false;
        }

        return $user->isAdmin() || $user->isOperator();
    }

    public function submit(User $user, MileageReconciliation $reconciliation): bool
    {
        if ($reconciliation->isApproved()) {
            return false;
        }

        return $user->isAdmin() || $user->isOperator() || $user->isDriver();
    }

    public function approve(User $user, MileageReconciliation $reconciliation): bool
    {
        // Drivers CANNOT approve reconciliations!
        return $user->isAdmin() || $user->isOperator();
    }

    public function reject(User $user, MileageReconciliation $reconciliation): bool
    {
        // Drivers CANNOT reject reconciliations!
        return $user->isAdmin() || $user->isOperator();
    }

    public function reopen(User $user, MileageReconciliation $reconciliation): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ?MileageReconciliation $reconciliation = null): bool
    {
        return $user->isAdmin();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isPurchasing();
    }

    public function uploadEvidence(User $user, MileageReconciliation $reconciliation): bool
    {
        return $user->isAdmin() || $user->isOperator() || $user->isDriver();
    }
}

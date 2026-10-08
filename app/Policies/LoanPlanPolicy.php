<?php

namespace App\Policies;

use App\Models\LoanPlan;
use App\Models\User;

/** Only the owner (administrator) sets interest rates and terms. */
class LoanPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, LoanPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LoanPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LoanPlan $plan): bool
    {
        // Plans already used by loans are deactivated instead, so loan history keeps its plan.
        return $user->isAdmin() && ! $plan->loans()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

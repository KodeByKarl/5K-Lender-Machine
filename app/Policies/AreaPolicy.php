<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

/** Areas are managed by the administrator only. */
class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->isAdmin()
            && ! $area->users()->exists()
            && ! $area->borrowers()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

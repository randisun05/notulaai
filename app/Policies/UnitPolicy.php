<?php

namespace App\Policies;

use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('superadmin');
    }
}

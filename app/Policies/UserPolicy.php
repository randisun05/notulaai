<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole('superadmin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasRole('superadmin') && $user->isNot($target);
    }
}

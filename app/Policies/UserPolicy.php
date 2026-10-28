<?php

namespace App\Policies;

use App\Models\User;

/**
 * Superadmin manages everyone. A unit admin manages the users of their own
 * unit, but never a superadmin account (and can't hand out that role, see
 * UserController::assignableRoles()).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function update(User $user, User $target): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->hasRole('admin')
            && filled($user->unit_id)
            && $target->unit_id === $user->unit_id
            && ! $target->hasRole('superadmin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isNot($target) && $this->update($user, $target);
    }
}

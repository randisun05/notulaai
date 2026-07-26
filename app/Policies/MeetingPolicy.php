<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Superadmin bisa akses semua rapat, selain itu hanya rapat di unit yang sama.
     */
    private function inScope(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('superadmin') || $meeting->unit_id === $user->unit_id;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function process(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return !$user->hasRole('user') && $this->inScope($user, $meeting);
    }
}

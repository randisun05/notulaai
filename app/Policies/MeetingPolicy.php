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

    /**
     * Mengesahkan / mengembalikan notulen resmi yang diajukan: pimpinan rapat
     * (kalau ia pengguna aplikasi), selain itu admin unit tsb; superadmin selalu
     * boleh. Tidak pernah orang yang mengajukannya sendiri.
     */
    public function approveMinutes(User $user, Meeting $meeting): bool
    {
        $minutes = $meeting->minutes;

        if (! $minutes || $minutes->submitted_by === $user->id || ! $this->inScope($user, $meeting)) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $minutes->chairperson_id
            ? $minutes->chairperson_id === $user->id
            : $user->hasRole('admin');
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return ! $user->hasRole('user') && $this->inScope($user, $meeting);
    }
}

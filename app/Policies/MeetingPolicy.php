<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

/**
 * Melihat: unit sendiri, atau semua unit bagi superadmin & pimpinan.
 * Mengubah/berpartisipasi (proses, forum, chat, penanda, daftar hadir, notulen):
 * hanya anggota unit rapat itu (dan superadmin) — pimpinan di unit lain hanya membaca.
 */
class MeetingPolicy
{
    private function inUnit(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('superadmin') || $meeting->unit_id === $user->unit_id;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->seesAllUnits() || $meeting->unit_id === $user->unit_id;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->inUnit($user, $meeting);
    }

    public function process(User $user, Meeting $meeting): bool
    {
        return $this->inUnit($user, $meeting);
    }

    /**
     * Mengesahkan / mengembalikan notulen resmi yang diajukan: pimpinan rapat
     * (kalau ia pengguna aplikasi), selain itu admin unit tsb; superadmin selalu
     * boleh. Tidak pernah orang yang mengajukannya sendiri.
     */
    public function approveMinutes(User $user, Meeting $meeting): bool
    {
        $minutes = $meeting->minutes;

        if (! $minutes || $minutes->submitted_by === $user->id) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Pimpinan rapat yang ditunjuk boleh dari unit lain (mis. pimpinan instansi).
        if ($minutes->chairperson_id) {
            return $minutes->chairperson_id === $user->id;
        }

        return $user->hasRole('admin') && $this->inUnit($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']) && $this->inUnit($user, $meeting);
    }
}

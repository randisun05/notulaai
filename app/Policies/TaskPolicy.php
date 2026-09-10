<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->inScope($user, $task);
    }

    /**
     * Task bisa terbentuk otomatis lewat konversi Action Item (siapa saja yang
     * boleh update meeting sumbernya) ATAU dibuat manual lewat form (admin/
     * superadmin saja — lihat manage()). create() di sini menjaga jalur konversi
     * Action Item tetap terbuka untuk semua unit member, tidak dibatasi manage().
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->assignee_id || $this->inScope($user, $task);
    }

    /**
     * Buat/edit task manual lewat form (judul, deskripsi, prioritas, deadline,
     * assignee) — sengaja dibatasi admin/superadmin, beda dari update() di atas
     * yang untuk operasional harian (status/SLA/disposisi) oleh siapa pun di unit.
     */
    public function manage(User $user, ?Task $task = null): bool
    {
        if (! $user->hasRole('admin') && ! $user->hasRole('superadmin')) {
            return false;
        }

        return $task ? $this->inScope($user, $task) : true;
    }

    /**
     * Hanya admin/superadmin unit yang bersangkutan yang boleh menyetujui atau
     * menolak Task yang statusnya "Review" — assignee sendiri tidak boleh
     * menyetujui pekerjaannya sendiri.
     */
    public function approve(User $user, Task $task): bool
    {
        return ($user->hasRole('admin') || $user->hasRole('superadmin')) && $this->inScope($user, $task);
    }

    private function inScope(User $user, Task $task): bool
    {
        return $user->hasRole('superadmin') || $task->unit_id === $user->unit_id;
    }
}

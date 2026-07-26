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
     * Task hanya dibuat lewat konversi Action Item (lihat MeetingPolicy::update
     * pada meeting sumbernya), bukan form manual — jadi create() di sini hanya
     * dipakai sebagai pengaman tambahan bila endpoint lain menambahkannya nanti.
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

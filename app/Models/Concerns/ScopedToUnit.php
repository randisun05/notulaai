<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sumber tunggal aturan "boleh lihat data unit siapa saja" (superadmin lintas unit,
 * selain itu unit sendiri) — sebelumnya diulang sebagai closure ->when(...) manual
 * di tiap controller, rawan typo kolom atau lupa ditambahkan pada query baru.
 */
trait ScopedToUnit
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        return $query->where($this->getTable().'.unit_id', $user->unit_id);
    }
}

<?php

namespace App\Services\Forum;

use App\Models\User;
use Illuminate\Support\Collection;

class MentionParser
{
    /**
     * Cari user mana saja dari daftar kandidat yang disebut lewat "@NamaLengkap"
     * (case-insensitive) di dalam body komentar.
     *
     * @param Collection<int, User> $candidates
     * @return Collection<int, User>
     */
    public function extract(string $body, Collection $candidates): Collection
    {
        $lowerBody = strtolower($body);

        return $candidates
            ->filter(fn (User $user) => $user->name && str_contains($lowerBody, '@' . strtolower($user->name)))
            ->values();
    }
}

<?php

namespace App\Support;

/**
 * Portable "contains" LIKE patterns. "!" is the escape character because it
 * behaves the same on MySQL, SQLite and Postgres (a backslash does not), so
 * use it as: whereRaw("col like ? escape '!'", [Like::contains($term)]).
 */
final class Like
{
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}

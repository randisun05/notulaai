<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

     protected $fillable = ['name'];

    /**
     * Mendapatkan semua user yang ada di unit ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Mendapatkan semua rapat yang dimiliki oleh unit ini.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}

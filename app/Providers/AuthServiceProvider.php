<?php

namespace App\Providers;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Policies\MeetingPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Unit::class => UnitPolicy::class,
        Meeting::class => MeetingPolicy::class,
        Task::class => TaskPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Dipakai oleh middleware route `can:access-admin-panel` (bukan Policy karena
        // bukan otorisasi atas sebuah model spesifik).
        Gate::define('access-admin-panel', function (User $user) {
            return $user->hasRole('superadmin');
        });
    }
}

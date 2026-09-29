<?php

namespace App\Providers;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Policies\ForumCommentPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        ForumComment::class => ForumCommentPolicy::class,
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

        // Dasbor pimpinan lintas unit (hanya baca).
        Gate::define('view-leadership-dashboard', fn (User $user) => $user->seesAllUnits());
    }
}

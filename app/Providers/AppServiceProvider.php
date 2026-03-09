<?php

namespace App\Providers;

use App\Models\CodingProblem;
use App\Models\Exam;
use App\Models\ProjectAssignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Admin can do anything
        Gate::before(fn($user) => $user->hasRole('admin') ? true : null);

        // Guru can create/update/delete their own resources
        Gate::define('create', fn($user, $model) =>
            $user->hasRole('guru')
        );

        Gate::define('update', fn($user, $model) =>
            $user->hasRole('guru') || $user->hasRole('admin')
        );

        Gate::define('delete', fn($user, $model) =>
            $user->hasRole('guru') || $user->hasRole('admin')
        );

        Gate::define('grade', fn($user, $model) =>
            $user->hasRole('guru') || $user->hasRole('admin')
        );
    }
}

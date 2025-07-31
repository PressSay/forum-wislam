<?php

namespace App\Providers;

use App\Policies\CategoryPolicy;
use App\Policies\PostPolicy;
use App\Policies\ThreadPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('have-blocked-from-category', [UserPolicy::class, 'isBlockedFromCategory']);
        Gate::define("user-blocked-by-user", [UserPolicy::class, 'userBlockedByUser']);
        Gate::define('view-page-admin-or-moderator', [UserPolicy::class, 'viewModeratorOrAdmin']);
        Gate::define("view-page-admin", [UserPolicy::class, 'viewAdmin']);
        Gate::define('view-page-moderator', [UserPolicy::class, 'viewModerator']);
        Gate::define('moderator-have-category', [UserPolicy::class, 'ModeratorHaveCategory']);
        Gate::define('update-post', [PostPolicy::class, 'update']);
        Gate::define("update-category", [CategoryPolicy::class, 'update']);
        Gate::define('update-thread', [ThreadPolicy::class, 'update']);
        Gate::define('pin-or-lock-thread', [ThreadPolicy::class, 'pinOrLock']);
    }
}

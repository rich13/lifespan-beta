<?php

namespace App\Providers;

use App\Models\Span;
use App\Models\User;
use App\Policies\SpanPolicy;
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
        Span::class => SpanPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define gate for Laravel Pulse access
        Gate::define('viewPulse', function ($user = null) {
            return $this->app->environment('local', 'testing') || ($user && $user->is_admin);
        });
    }
}

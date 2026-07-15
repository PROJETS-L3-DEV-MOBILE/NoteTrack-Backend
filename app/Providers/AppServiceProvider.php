<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // Requis pour le passage en UUID : sans ce binding, Sanctum utilise son
        // modèle par défaut avec un id bigint, incompatible avec users.id (uuid).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Scramble protège /docs/api hors environnement local via ce Gate.
        // Doc rendue publique en prod (API interne / projet).
        Gate::define('viewApiDocs', fn (?User $user): bool => true);
    }
}
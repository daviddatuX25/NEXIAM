<?php

namespace App\Providers;

use App\Auth\Pipeline\RedirectIfStaffTwoFactorAuthenticatable;
use App\Auth\Pipeline\ValidateStaffPrimaryCredentials;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::authenticateThrough(fn () => [
            ValidateStaffPrimaryCredentials::class,
            RedirectIfStaffTwoFactorAuthenticatable::class,
        ]);
    }
}

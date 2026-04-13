<?php

namespace App\Providers;

use App\Models\Category;
use App\Observers\CategoryObserver;
use App\Support\RbacPermissionMatrix;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\ServiceProvider;

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
        Category::observe(CategoryObserver::class);

        FilamentShield::buildPermissionKeyUsing(
            fn (string $entity, string $affix, string $subject): string => RbacPermissionMatrix::buildShieldPermissionKey(
                $entity,
                $affix,
                $subject,
            ),
        );

        FilamentShield::prohibitDestructiveCommands($this->app->isProduction());
    }
}

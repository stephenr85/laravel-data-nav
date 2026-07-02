<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\InvocableRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-data-nav');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(NavMatcher::class, PathNavMatcher::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(InvocableRegistry::class)->register(
            $this->app->make(ResolveNav::class),
        );
    }
}

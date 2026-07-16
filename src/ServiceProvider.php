<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;
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

        // The expansion strategy defaults to the original invocable-item
        // behavior; a host swaps this to teach resolution a custom kind's
        // subtree. Kept a singleton so a host's registered custom kinds hold.
        $this->app->bind(NavExpander::class, InvokableNavExpander::class);

        // The open kind registry, pre-seeded with the two built-ins; a host
        // registers additional kinds so its custom nodes survive the morph
        // round-trip. Singleton so registrations persist for the request.
        $this->app->singleton(NavKindRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(InvocableRegistry::class)->register(
            $this->app->make(ResolveNav::class),
        );
    }
}

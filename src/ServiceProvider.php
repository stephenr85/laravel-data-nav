<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\Registries\RegistryIndex;
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

        // The gate pipeline, bound EMPTY by default — no stages means every node
        // passes (today's everything-visible behavior). A host resolves this
        // singleton and pushes its ordered stages (e.g. entitlement, then
        // permission) so the same instance backs both registration and build.
        $this->app->singleton(NavGate::class);

        // The keyed registry of named navigations — a singleton the host
        // populates with its navigation factories (e.g. `tenant`, `admin`). It
        // composes the gate, the bound expander, and the bound matcher.
        $this->app->singleton(NavRegistry::class);

        // data-nav's own branch of the keyspace. It replaces a write into the estate-wide
        // InvocableRegistry singleton: `data-nav.resolve` was pooled with every other package's
        // capabilities, and is owned now (registry-kernel ticket 40).
        $this->app->singleton(NavInvocableRegistry::class);
    }

    public function packageBooted(): void
    {
        $registry = $this->app->make(NavInvocableRegistry::class)->register(
            $this->app->make(ResolveNav::class),
        );

        // An owner registers DOWN into the index from its own boot; the index never reaches up.
        $index = $this->app->make(RegistryIndex::class);
        $index->describe($registry);

        // `data-nav.navigations` — the keyed navigations, a branch beside the capabilities. Described
        // after the capability root so the index reads foundation-first (registry-kernel ticket 38).
        // Nothing fills this from here: the HOST registers its navigations, and it does so after this
        // boot, so describing an empty registry is the correct and expected state.
        $index->describe($this->app->make(NavRegistry::class), by: self::class);
    }
}

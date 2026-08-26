<?php

use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavInvocableRegistry;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\RegistryIndex;

it('owns the `data-nav` branch and describes it down into the index', function () {
    $index = app(RegistryIndex::class);

    // The direction rule: data-nav's provider registered DOWN into the index. The index never
    // scanned for it, and knows nothing about this package beyond what it was handed.
    expect($index->owner('data-nav'))->toBeInstanceOf(NavInvocableRegistry::class);
});

it('routes `data-nav.resolve` to this registry by longest prefix, not by name', function () {
    $store = app(RegistryIndex::class)->routeTo(Key::parse('data-nav.resolve'));

    expect($store)->not->toBeNull()
        ->and($store->has('data-nav.resolve'))->toBeTrue();
});

it('keeps the capability spelled as it always was, because it was already under the root', function () {
    // `door()` stamps only a key that is not already beneath the declared root. `data-nav.resolve`
    // is, so nothing moved — what changed is that the key is owned rather than pooled.
    expect(array_map('strval', app(NavInvocableRegistry::class)->keys()))
        ->toContain('data-nav.resolve')
        ->and(app(NavInvocableRegistry::class)->names())->toContain('resolve');
});

it('shares ONE RegistryIndex across the container — the tripwire', function () {
    // Without Rushing\Popcorn\Laravel\PopcornServiceProvider in the harness, every make()
    // yields a throwaway index and every describe() lands on nothing, silently (27 D3).
    expect(app(RegistryIndex::class))->toBe(app(RegistryIndex::class));
});

it('describes `data-nav.navigations` down into the index, beside the capabilities', function () {
    $index = app(RegistryIndex::class);

    expect($index->owner('data-nav.navigations'))->toBeInstanceOf(NavRegistry::class)
        // The capability root is untouched: two branches, two owners, longest prefix routes.
        ->and($index->owner('data-nav'))->toBeInstanceOf(NavInvocableRegistry::class);
});

it('round-trips register() -> build() through the port vocabulary, keys stamped under the root', function () {
    $registry = app(NavRegistry::class);

    $registry->register('sidebar', fn (): array => [NavLink::make('Home', '/home')]);

    expect($registry->has('sidebar'))->toBeTrue()
        ->and($registry->names())->toContain('sidebar')
        ->and(array_map('strval', $registry->keys()))->toContain('data-nav.navigations.sidebar')
        // routing by longest prefix reaches THIS registry, not the capability one
        ->and(app(RegistryIndex::class)->routeTo(Key::parse('data-nav.navigations.sidebar')))->toBe($registry);

    $tree = $registry->build('sidebar', new NavContext);

    expect($tree->items)->toHaveCount(1);
});

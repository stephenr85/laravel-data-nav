<?php

use Rushing\DataNav\NavInvocableRegistry;
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

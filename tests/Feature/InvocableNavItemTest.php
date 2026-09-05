<?php

use Rushing\DataNav\Contracts\NavItem;
use Rushing\DataNav\InvocableNavItem;
use Rushing\DataNav\NavInvocableRegistry;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavTree;
use Rushing\Popcorn\Invocables\LocalInvocable;
use Schemastud\DataSchemas\Contracts\SchemaIdentity;

it('is a SchemaIdentity NavItem carrying an invocable name and input', function () {
    $item = InvocableNavItem::make(
        title: 'Topics',
        invocable: 'publishing/topics',
        input: ['depth' => 2],
    );

    expect($item)->toBeInstanceOf(NavItem::class)
        ->and($item)->toBeInstanceOf(SchemaIdentity::class)
        ->and(InvocableNavItem::schemaName())->toBe('nav/invocable-item')
        ->and(InvocableNavItem::schemaVersion())->toBe(1)
        ->and($item->invocable)->toBe('publishing/topics')
        ->and($item->input)->toBe(['depth' => 2]);

    $array = $item->toArray();
    expect($array['kind'])->toBe('nav/invocable-item')
        ->and($array['invocable'])->toBe('publishing/topics');
});

it('round-trips a mixed tree as a discriminable union of node kinds', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Home', href: '/'),
        InvocableNavItem::make(title: 'Topics', invocable: 'publishing/topics'),
    ]);

    $rehydrated = NavTree::from(json_decode($tree->toJson(), true));

    expect($rehydrated->items[0])->toBeInstanceOf(NavLink::class)
        ->and($rehydrated->items[1])->toBeInstanceOf(InvocableNavItem::class)
        ->and($rehydrated->items[1]->invocable)->toBe('publishing/topics');
});

it('builds its own children on resolve and stamps active-state over the expansion', function () {
    app(NavInvocableRegistry::class)->register(new LocalInvocable(
        'test.topics',
        fn (array $input): array => ['items' => [
            NavLink::make(title: 'Alpha', href: '/topics/alpha')->toArray(),
            NavLink::make(title: 'Beta', href: '/topics/beta')->toArray(),
        ]],
    ));

    $tree = NavTree::make([
        InvocableNavItem::make(title: 'Topics', invocable: 'test.topics'),
    ]);

    $output = app(NavInvocableRegistry::class)->invoke('data-nav.resolve', [
        'tree' => $tree->toArray(),
        'path' => 'topics/alpha',
    ]);

    $resolved = NavTree::from($output['tree']);
    $topics = $resolved->items[0];

    expect($topics)->toBeInstanceOf(InvocableNavItem::class)
        ->and($topics->children())->toHaveCount(2)
        ->and($topics->isActiveTrail())->toBeTrue()
        ->and($topics->children()[0]->title())->toBe('Alpha')
        ->and($topics->children()[0]->isActive())->toBeTrue()
        ->and($topics->children()[1]->isActive())->toBeFalse();
});

it('expands recursively when a built child is itself invocable-backed', function () {
    $registry = app(NavInvocableRegistry::class);

    $registry->register(new LocalInvocable('test.outer', fn (array $input): array => ['items' => [
        InvocableNavItem::make(title: 'Inner', invocable: 'test.inner')->toArray(),
    ]]));
    $registry->register(new LocalInvocable('test.inner', fn (array $input): array => ['items' => [
        NavLink::make(title: 'Leaf', href: '/leaf')->toArray(),
    ]]));

    $tree = NavTree::make([InvocableNavItem::make(title: 'Outer', invocable: 'test.outer')]);

    $output = $registry->invoke('data-nav.resolve', [
        'tree' => $tree->toArray(),
        'path' => 'leaf',
    ]);

    $resolved = NavTree::from($output['tree']);
    $inner = $resolved->items[0]->children()[0];

    expect($inner)->toBeInstanceOf(InvocableNavItem::class)
        ->and($inner->children())->toHaveCount(1)
        ->and($inner->children()[0]->title())->toBe('Leaf')
        ->and($inner->children()[0]->isActive())->toBeTrue();
});

it('degrades an unknown invocable name to empty children, not an error', function () {
    $tree = NavTree::make([
        InvocableNavItem::make(title: 'Ghost', invocable: 'no.such-capability'),
    ]);

    $output = app(NavInvocableRegistry::class)->invoke('data-nav.resolve', [
        'tree' => $tree->toArray(),
        'path' => 'anywhere',
    ]);

    $resolved = NavTree::from($output['tree']);

    expect($resolved->items[0])->toBeInstanceOf(InvocableNavItem::class)
        ->and($resolved->items[0]->children())->toBe([]);
});

it('degrades an UNPARSEABLE invocable name the same way, because a tree is data', function () {
    // A name that is not merely unregistered but illegal as a registry key — the shape a host's
    // hand-written JSON produces. It must land as an absent capability, not as an InvalidRegistryKey
    // blaming a developer in another package.
    $tree = NavTree::make([
        InvocableNavItem::make(title: 'Ghost', invocable: 'Not/A Key'),
    ]);

    $output = app(NavInvocableRegistry::class)->invoke('data-nav.resolve', [
        'tree' => $tree->toArray(),
        'path' => 'anywhere',
    ]);

    $resolved = NavTree::from($output['tree']);

    expect($resolved->items[0])->toBeInstanceOf(InvocableNavItem::class)
        ->and($resolved->items[0]->children())->toBe([]);
});

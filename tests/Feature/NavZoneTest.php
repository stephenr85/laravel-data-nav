<?php

use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavNode;
use Rushing\DataNav\NavRegistry;
use Rushing\DataNav\NavTree;

// ux-walkthrough M4 (UX-08): a top-level node says which ZONE a renderer draws it in. `primary` is the rail;
// `meta` is the Developer zone. It is a wire field, not `meta`, because `meta` is #[Hidden] and never leaves the server.
it('carries an optional zone that defaults to none, and inZone() sets it without touching anything else', function () {
    $node = NavLink::make(title: 'Ops', href: '/ops');

    expect($node->zone)->toBeNull()
        ->and($node->toArray()['zone'])->toBeNull();

    $zoned = $node->inZone('meta');

    expect($zoned->zone)->toBe('meta')
        ->and($zoned->title)->toBe('Ops')
        ->and($node->zone)->toBeNull();
});

it('keeps the zone through stamped() and through the registry build round-trip', function () {
    $registry = app(NavRegistry::class);
    $registry->register('zoned', fn (): array => [
        NavLink::make(title: 'Developer', routeName: 'developer.section')
            ->inZone('meta')
            ->stamped(active: false, activeTrail: false, children: [NavLink::make(title: 'Schemas', href: '/schemas')]),
        NavLink::make(title: 'Home', href: '/')->inZone('primary'),
    ]);

    $tree = $registry->build('zoned', new NavContext);
    $wire = $tree->toArray()['items'];

    expect(array_column($wire, 'zone'))->toBe(['meta', 'primary'])
        ->and($wire[0]['children'][0]['zone'])->toBeNull();
});

it('round-trips a zone through from()', function () {
    $tree = NavTree::from(['items' => [['kind' => 'nav/link', 'title' => 'Home', 'href' => '/', 'zone' => 'primary']]]);

    expect($tree->items[0])->toBeInstanceOf(NavNode::class)
        ->and($tree->items[0]->zone)->toBe('primary');
});

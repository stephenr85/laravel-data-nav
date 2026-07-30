<?php

use Rushing\DataNav\InvokableNavItem;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavTree;

// Ticket 01: NavNode carries an opaque, server-only gate-meta bag the host
// stashes its gating tokens into. It is build-time only — readable in-process,
// but never serialized (ADR-0119: no gate/host vocabulary leaks to the client).

it('exposes gate-meta in-process but omits it from the serialized array', function () {
    $link = NavLink::make(title: 'Studio', href: '/studio')
        ->withMeta(['entitlement' => ['composition.content'], 'permission' => 'studio.view']);

    // Readable server-side.
    expect($link->meta)->toBe(['entitlement' => ['composition.content'], 'permission' => 'studio.view']);

    // Absent from every serialized shape.
    $array = $link->toArray();
    expect($array)->not->toHaveKey('meta')
        ->and($link->toJson())->not->toContain('entitlement')
        ->and($link->toJson())->not->toContain('permission');
});

it('omits gate-meta on an InvokableNavItem too', function () {
    $item = InvokableNavItem::make(title: 'Platform', invocable: 'frame/resources')
        ->withMeta(['permission' => 'platform.view']);

    expect($item->meta)->toBe(['permission' => 'platform.view'])
        ->and($item->toArray())->not->toHaveKey('meta')
        ->and($item->toJson())->not->toContain('platform.view');
});

it('leaves a node with no meta unchanged', function () {
    $link = NavLink::make(title: 'Knowledge', href: '/knowledge');

    expect($link->meta)->toBe([])
        ->and($link->toArray())->not->toHaveKey('meta');
});

it('does not carry meta across the morph round-trip — a rehydrated node has empty meta', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Studio', href: '/studio')->withMeta(['entitlement' => ['composition.content']]),
        InvokableNavItem::make(title: 'Platform', invocable: 'frame/resources')->withMeta(['permission' => 'platform.view']),
    ]);

    // The wire never carried the meta, so it cannot leak on the way back in.
    expect($tree->toJson())->not->toContain('entitlement')
        ->and($tree->toJson())->not->toContain('permission');

    $rehydrated = NavTree::from(json_decode($tree->toJson(), true));

    expect($rehydrated->items[0])->toBeInstanceOf(NavLink::class)
        ->and($rehydrated->items[0]->meta)->toBe([])
        ->and($rehydrated->items[1])->toBeInstanceOf(InvokableNavItem::class)
        ->and($rehydrated->items[1]->invocable)->toBe('frame/resources')
        ->and($rehydrated->items[1]->meta)->toBe([]);
});

it('preserves meta on a stamped server-side clone', function () {
    $link = NavLink::make(title: 'Studio', href: '/studio')
        ->withMeta(['entitlement' => ['composition.content']]);

    $stamped = $link->stamped(active: true, activeTrail: false, children: []);

    // The gate pass reads meta off the stamped node, yet the eventual
    // serialized output still omits it.
    expect($stamped->meta)->toBe(['entitlement' => ['composition.content']])
        ->and($stamped->isActive())->toBeTrue()
        ->and($stamped->toArray())->not->toHaveKey('meta');
});

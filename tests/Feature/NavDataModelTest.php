<?php

use Rushing\DataNav\Contracts\NavItem;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavTree;
use Rushing\LaravelDataSchemas\Contracts\SchemaIdentity;

it('exposes stable schema identity on the node kinds and the tree', function () {
    expect(NavLink::schemaName())->toBe('nav/link')
        ->and(NavLink::schemaVersion())->toBe(1)
        ->and(NavTree::schemaName())->toBe('nav/tree')
        ->and(NavTree::schemaVersion())->toBe(1);

    expect(new NavLink(kind: 'nav/link', title: 'Home'))->toBeInstanceOf(SchemaIdentity::class);
    expect(new NavTree)->toBeInstanceOf(SchemaIdentity::class);
});

it('models a NavLink as the NavItem contract', function () {
    $link = NavLink::make(title: 'Blog', href: '/blog', match: 'blog/*');

    expect($link)->toBeInstanceOf(NavItem::class)
        ->and($link->title())->toBe('Blog')
        ->and($link->href())->toBe('/blog')
        ->and($link->matchPattern())->toBe('blog/*')
        ->and($link->isActive())->toBeFalse()
        ->and($link->isActiveTrail())->toBeFalse()
        ->and($link->children())->toBe([]);
});

it('round-trips a nested tree to array with children preserved', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Home', href: '/'),
        NavLink::make(title: 'Products', href: '/products', children: [
            NavLink::make(title: 'Widgets', href: '/products/widgets'),
        ]),
    ]);

    $array = $tree->toArray();

    expect($array['items'])->toHaveCount(2)
        ->and($array['items'][0]['kind'])->toBe('nav/link')
        ->and($array['items'][0]['title'])->toBe('Home')
        ->and($array['items'][1]['title'])->toBe('Products')
        ->and($array['items'][1]['children'])->toHaveCount(1)
        ->and($array['items'][1]['children'][0]['title'])->toBe('Widgets')
        ->and($array['items'][1]['children'][0]['kind'])->toBe('nav/link');
});

it('round-trips a nested tree through JSON and rehydrates concrete node kinds', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Products', href: '/products', children: [
            NavLink::make(title: 'Widgets', href: '/products/widgets'),
        ]),
    ]);

    $json = $tree->toJson();

    $rehydrated = NavTree::from(json_decode($json, true));

    expect($rehydrated->items)->toHaveCount(1)
        ->and($rehydrated->items[0])->toBeInstanceOf(NavLink::class)
        ->and($rehydrated->items[0]->title())->toBe('Products')
        ->and($rehydrated->items[0]->children())->toHaveCount(1)
        ->and($rehydrated->items[0]->children()[0])->toBeInstanceOf(NavLink::class)
        ->and($rehydrated->items[0]->children()[0]->title())->toBe('Widgets');
});

<?php

declare(strict_types=1);

use Rushing\DataNav\NavItem;
use Rushing\DataNav\NavTree;

it('serializes a nested tree to an array with children preserved', function () {
    $tree = NavTree::make([
        NavItem::make('Blog', '/blog', match: 'blog/*', children: [
            NavItem::make('My Post', '/blog/my-post'),
        ]),
    ]);

    $array = $tree->toArray();

    expect($array)->toHaveKey('items');
    expect($array['items'])->toHaveCount(1);

    $blog = $array['items'][0];
    expect($blog['title'])->toBe('Blog');
    expect($blog['href'])->toBe('/blog');
    expect($blog['match'])->toBe('blog/*');
    expect($blog['active'])->toBeFalse();
    expect($blog['activeTrail'])->toBeFalse();

    // Nested children round-trip as nested arrays.
    expect($blog['children'])->toHaveCount(1);
    expect($blog['children'][0]['title'])->toBe('My Post');
    expect($blog['children'][0]['href'])->toBe('/blog/my-post');
});

it('serializes a nested tree to JSON with children preserved', function () {
    $tree = NavTree::make([
        NavItem::make('Blog', '/blog', children: [
            NavItem::make('My Post', '/blog/my-post'),
        ]),
    ]);

    $decoded = json_decode($tree->toJson(), true);

    expect($decoded['items'][0]['title'])->toBe('Blog');
    expect($decoded['items'][0]['children'][0]['title'])->toBe('My Post');
    expect($decoded['items'][0]['children'][0]['href'])->toBe('/blog/my-post');
});

it('serializes a NavItem on its own to both array and JSON', function () {
    $item = NavItem::make('About', '/about');

    expect($item->toArray())->toMatchArray([
        'title' => 'About',
        'href' => '/about',
        'match' => null,
        'children' => [],
        'active' => false,
        'activeTrail' => false,
    ]);

    $decoded = json_decode($item->toJson(), true);
    expect($decoded['title'])->toBe('About');
    expect($decoded['children'])->toBe([]);
});

it('reports whether an item has children', function () {
    expect(NavItem::make('Leaf', '/leaf')->hasChildren())->toBeFalse();
    expect(
        NavItem::make('Branch', '/branch', children: [NavItem::make('Leaf', '/branch/leaf')])
            ->hasChildren()
    )->toBeTrue();
});

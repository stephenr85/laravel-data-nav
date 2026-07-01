<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Rushing\DataNav\NavItem;
use Rushing\DataNav\NavResolver;
use Rushing\DataNav\NavTree;

function fakeRequest(string $path): Request
{
    return Request::create($path, 'GET');
}

it('stamps active on the leaf and activeTrail on its ancestor', function () {
    $tree = NavTree::make([
        NavItem::make('Home', '/'),
        NavItem::make('Blog', '/blog', match: 'blog/*', children: [
            NavItem::make('My Post', '/blog/my-post'),
            NavItem::make('Other Post', '/blog/other-post'),
        ]),
        NavItem::make('About', '/about'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/blog/my-post'));

    $blog = $resolved->items[1];
    $leaf = $blog->children[0];
    $sibling = $blog->children[1];
    $about = $resolved->items[2];

    // The matching leaf is active.
    expect($leaf->active)->toBeTrue();
    expect($leaf->activeTrail)->toBeFalse();

    // The parent section lights up its trail (and is itself active via glob).
    expect($blog->activeTrail)->toBeTrue();
    expect($blog->active)->toBeTrue();

    // An unrelated sibling has neither flag.
    expect($sibling->active)->toBeFalse();
    expect($sibling->activeTrail)->toBeFalse();

    // An unrelated top-level item has neither flag.
    expect($about->active)->toBeFalse();
    expect($about->activeTrail)->toBeFalse();
});

it('lights an ancestor trail even when the parent itself does not match', function () {
    $tree = NavTree::make([
        NavItem::make('Docs', '/docs', children: [
            NavItem::make('Guide', '/docs/guide'),
        ]),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/docs/guide'));

    $docs = $resolved->items[0];

    // Parent href does not exactly match /docs/guide, so it is not active...
    expect($docs->active)->toBeFalse();
    // ...but it is on the active trail to its active child.
    expect($docs->activeTrail)->toBeTrue();
    expect($docs->children[0]->active)->toBeTrue();
});

it('activates a match glob pattern for a matching path', function () {
    $tree = NavTree::make([
        NavItem::make('Blog', '/blog', match: 'blog/*'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/blog/nested/deep'));

    expect($resolved->items[0]->active)->toBeTrue();
});

it('does not activate a glob pattern for a non-matching path', function () {
    $tree = NavTree::make([
        NavItem::make('Blog', '/blog', match: 'blog/*'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/about'));

    expect($resolved->items[0]->active)->toBeFalse();
});

it('falls back to an exact href match when match is null', function () {
    $tree = NavTree::make([
        NavItem::make('About', '/about'),
        NavItem::make('About Team', '/about/team'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/about'));

    // Exact match hits /about only, not the deeper /about/team.
    expect($resolved->items[0]->active)->toBeTrue();
    expect($resolved->items[1]->active)->toBeFalse();
});

it('matches the root href against the root path', function () {
    $tree = NavTree::make([
        NavItem::make('Home', '/'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree, fakeRequest('/'));

    expect($resolved->items[0]->active)->toBeTrue();
});

it('defaults to the container request when none is passed', function () {
    $this->app->instance('request', fakeRequest('/about'));

    $tree = NavTree::make([
        NavItem::make('About', '/about'),
    ]);

    $resolved = app(NavResolver::class)->resolve($tree);

    expect($resolved->items[0]->active)->toBeTrue();
});

it('leaves the source tree untouched (immutable stamping)', function () {
    $tree = NavTree::make([
        NavItem::make('About', '/about'),
    ]);

    app(NavResolver::class)->resolve($tree, fakeRequest('/about'));

    expect($tree->items[0]->active)->toBeFalse();
    expect($tree->items[0]->activeTrail)->toBeFalse();
});

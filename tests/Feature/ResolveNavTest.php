<?php

use Illuminate\Http\Request;
use Rushing\DataNav\Nav;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavTree;
use Rushing\DataNav\PathNavMatcher;
use Rushing\DataNav\ResolveNav;
use Rushing\Popcorn\Binding;
use Rushing\Popcorn\InvocableRegistry;

it('matches a glob pattern, falls back to exact href, and rejects non-matches', function () {
    $matcher = new PathNavMatcher;

    $glob = NavLink::make(title: 'Blog', href: '/blog', match: 'blog/*');
    expect($matcher->matches($glob, 'blog/first-post'))->toBeTrue()
        ->and($matcher->matches($glob, 'about'))->toBeFalse();

    $exact = NavLink::make(title: 'About', href: '/about');
    expect($matcher->matches($exact, 'about'))->toBeTrue()
        ->and($matcher->matches($exact, 'about/team'))->toBeFalse();

    $parent = NavLink::make(title: 'Section');
    expect($matcher->matches($parent, 'section'))->toBeFalse();
});

it('is registered as a local invocable named data-nav/resolve', function () {
    $registry = app(InvocableRegistry::class);

    expect($registry->has('data-nav/resolve'))->toBeTrue();

    $resolve = app(ResolveNav::class);
    expect($resolve->name())->toBe('data-nav/resolve')
        ->and($resolve->binding())->toBe(Binding::Local);
});

it('stamps active on the matching leaf and activeTrail on its ancestors via the registry', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Home', href: '/'),
        NavLink::make(title: 'Products', href: '/products', children: [
            NavLink::make(title: 'Widgets', href: '/products/widgets'),
            NavLink::make(title: 'Gadgets', href: '/products/gadgets'),
        ]),
    ]);

    $output = app(InvocableRegistry::class)->invoke('data-nav/resolve', [
        'tree' => $tree->toArray(),
        'path' => 'products/widgets',
    ]);

    $resolved = NavTree::from($output['tree']);

    [$home, $products] = $resolved->items;
    [$widgets, $gadgets] = $products->children();

    expect($home->isActive())->toBeFalse()
        ->and($home->isActiveTrail())->toBeFalse()
        ->and($products->isActive())->toBeFalse()
        ->and($products->isActiveTrail())->toBeTrue()
        ->and($widgets->isActive())->toBeTrue()
        ->and($widgets->isActiveTrail())->toBeFalse()
        ->and($gadgets->isActive())->toBeFalse()
        ->and($gadgets->isActiveTrail())->toBeFalse();
});

it('resolves active-state against a faked request through the Nav adapter', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Blog', href: '/blog', match: 'blog/*', children: [
            NavLink::make(title: 'First', href: '/blog/first'),
        ]),
        NavLink::make(title: 'About', href: '/about'),
    ]);

    $resolved = Nav::resolve($tree, Request::create('/blog/first'));

    [$blog, $about] = $resolved->items;

    expect($blog->isActive())->toBeTrue()
        ->and($blog->children()[0]->isActive())->toBeTrue()
        ->and($about->isActive())->toBeFalse()
        ->and($about->isActiveTrail())->toBeFalse();
});

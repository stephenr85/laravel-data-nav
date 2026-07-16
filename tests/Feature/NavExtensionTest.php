<?php

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\InvokableNavExpander;
use Rushing\DataNav\InvokableNavItem;
use Rushing\DataNav\NavKindRegistry;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavNode;
use Rushing\DataNav\NavTree;
use Rushing\DataNav\PathNavMatcher;
use Rushing\DataNav\ResolveNav;
use Rushing\Popcorn\InvocableRegistry;
use Spatie\LaravelData\Attributes\DataCollectionOf;

/**
 * A host-defined node kind — the case the kind registry exists to keep
 * hydration-safe. Before the registry, its `kind` fell through the closed
 * `morph()` match and its extra field (`badge`) was dropped on the round-trip.
 */
class HostBadgeNavItem extends NavNode
{
    public function __construct(
        string $title,
        public string $badge = '',
        ?string $href = null,
        ?string $match = null,
        #[DataCollectionOf(NavNode::class)]
        array $children = [],
        bool $active = false,
        bool $activeTrail = false,
        ?string $icon = null,
        ?string $routeName = null,
    ) {
        parent::__construct(
            kind: 'host/badge',
            title: $title,
            href: $href,
            match: $match,
            children: $children,
            active: $active,
            activeTrail: $activeTrail,
            icon: $icon,
            routeName: $routeName,
        );
    }
}

it('carries the additive icon and routeName fields through the round-trip', function () {
    $link = NavLink::make(
        title: 'Knowledge',
        href: '/knowledge',
        icon: 'Library',
        routeName: 'knowledge.index',
    );

    expect($link->icon)->toBe('Library')
        ->and($link->routeName)->toBe('knowledge.index');

    $rehydrated = NavTree::from(json_decode(NavTree::make([$link])->toJson(), true));

    expect($rehydrated->items[0]->icon)->toBe('Library')
        ->and($rehydrated->items[0]->routeName)->toBe('knowledge.index');
});

it('carries icon and routeName on an InvokableNavItem too', function () {
    $item = InvokableNavItem::make(
        title: 'Topics',
        invocable: 'publishing/topics',
        icon: 'Folder',
        routeName: 'topics',
    );

    expect($item->icon)->toBe('Folder')
        ->and($item->routeName)->toBe('topics');
});

it('pre-seeds the kind registry with the two built-in node kinds', function () {
    $registry = app(NavKindRegistry::class);

    expect($registry->resolve('nav/link'))->toBe(NavLink::class)
        ->and($registry->resolve('nav/invokable-item'))->toBe(InvokableNavItem::class)
        ->and($registry->resolve('unregistered'))->toBeNull()
        ->and($registry->resolve(null))->toBeNull();
});

it('keeps a host-registered custom kind hydration-safe through the morph round-trip', function () {
    app(NavKindRegistry::class)->register('host/badge', HostBadgeNavItem::class);

    $tree = NavTree::make([
        new HostBadgeNavItem(title: 'Inbox', badge: '3', href: '/inbox'),
    ]);

    $rehydrated = NavTree::from(json_decode($tree->toJson(), true));

    expect($rehydrated->items[0])->toBeInstanceOf(HostBadgeNavItem::class)
        ->and($rehydrated->items[0]->badge)->toBe('3')
        ->and($rehydrated->items[0]->title())->toBe('Inbox');
});

it('resolves active-state over a host kind once it is registered', function () {
    app(NavKindRegistry::class)->register('host/badge', HostBadgeNavItem::class);

    $tree = NavTree::make([
        new HostBadgeNavItem(title: 'Inbox', badge: '3', href: '/inbox'),
    ]);

    $output = app(InvocableRegistry::class)->invoke('data-nav/resolve', [
        'tree' => $tree->toArray(),
        'path' => 'inbox',
    ]);

    $resolved = NavTree::from($output['tree']);

    expect($resolved->items[0])->toBeInstanceOf(HostBadgeNavItem::class)
        ->and($resolved->items[0]->badge)->toBe('3')
        ->and($resolved->items[0]->isActive())->toBeTrue();
});

it('binds the default expander to the invocable-item strategy', function () {
    expect(app(NavExpander::class))
        ->toBeInstanceOf(InvokableNavExpander::class);
});

it('lets a host swap the expansion strategy for a custom node kind', function () {
    app(NavKindRegistry::class)->register('host/badge', HostBadgeNavItem::class);

    // A custom expander teaches resolution that a host kind builds a static
    // subtree — proving expansion is behavior-extensible, not just data. A host
    // binds this in a provider's register() (before data-nav boots ResolveNav);
    // here we construct the resolver directly with the swapped strategy.
    $expander = new class implements NavExpander
    {
        public function expand(NavNode $node): array
        {
            if ($node instanceof HostBadgeNavItem) {
                return [NavLink::make(title: 'Synthetic', href: '/synthetic')];
            }

            return $node->children();
        }
    };

    $resolve = new ResolveNav(new PathNavMatcher, $expander);

    $tree = NavTree::make([new HostBadgeNavItem(title: 'Inbox', href: '/inbox')]);

    $output = $resolve->invoke([
        'tree' => $tree->toArray(),
        'path' => 'synthetic',
    ]);

    $resolved = NavTree::from($output['tree']);
    $badge = $resolved->items[0];

    expect($badge->children())->toHaveCount(1)
        ->and($badge->children()[0]->title())->toBe('Synthetic')
        ->and($badge->children()[0]->isActive())->toBeTrue()
        ->and($badge->isActiveTrail())->toBeTrue();
});

<?php

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\InvokableNavExpander;
use Rushing\DataNav\InvokableNavItem;
use Rushing\DataNav\NavInvocableRegistry;
use Rushing\DataNav\NavKindRegistry;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavNode;
use Rushing\DataNav\NavTree;
use Rushing\DataNav\PathNavMatcher;
use Rushing\DataNav\ResolveNav;
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

// ⚠️ CHANGED with the popcorn declaration: `resolve()` is the kernel's now and THROWS on a miss, so the
// null-on-miss lookup this test is really about kept its behaviour and lost its name to `classFor()`.
// The null return is load-bearing — NavNode::morph() hands it to spatie, for which null means "fall back
// to default hydration" — so the split is asserted in both directions below.
it('pre-seeds the kind registry with the two built-in node kinds', function () {
    $registry = app(NavKindRegistry::class);

    expect($registry->classFor('nav/link'))->toBe(NavLink::class)
        ->and($registry->classFor('nav/invokable-item'))->toBe(InvokableNavItem::class)
        ->and($registry->classFor('unregistered'))->toBeNull()
        ->and($registry->classFor(null))->toBeNull()
        ->and($registry->all())->toBe([
            'nav/link' => NavLink::class,
            'nav/invokable-item' => InvokableNavItem::class,
        ]);
});

it('gives resolve() the kernel meaning, which throws where classFor() returns null', function () {
    $registry = app(NavKindRegistry::class);

    expect($registry->resolve('nav/link'))->toBe(NavLink::class)
        ->and($registry->has('nav/link'))->toBeTrue()
        ->and($registry->has('unregistered'))->toBeFalse();

    expect(fn () => $registry->resolve('unregistered'))
        ->toThrow(Rushing\Popcorn\Registries\Exceptions\RegistryMiss::class);
});

it('declares data-nav.kinds and reaches the shared index at boot', function () {
    $index = app(Rushing\Popcorn\Registries\RegistryIndex::class);

    // routeTo() is the read that matters: declaring and indexing are two acts, and a class that declares
    // and is never described is invisible to `popcorn:registries` exactly as if it had not declared.
    //
    // ⚠️ This assertion is discriminating for a sharper reason than "else it would be null".
    // NavInvocableRegistry owns the PARENT root `data-nav`, so the index routes every unowned key under
    // it — including `data-nav.kinds` — to that registry. Nesting is what makes the check strict: had
    // this branch not been described, `routeTo('data-nav.kinds')` would have returned a perfectly real
    // NavInvocableRegistry rather than null, and a `not->toBeNull()` test would have passed against
    // nothing being described at all.
    expect($index->routeTo('data-nav.kinds'))->toBeInstanceOf(NavKindRegistry::class)
        ->and($index->declarationAt('data-nav.kinds')?->root)->toBe('data-nav.kinds')
        // The nearest-owner fallback, asserted so the line above is read as the narrower claim it is.
        ->and($index->routeTo('data-nav.no-such-branch'))->toBeInstanceOf(NavInvocableRegistry::class);
});

it('keeps a slash-bearing kind spelled exactly as it ships, because the key IS the wire discriminator', function () {
    $registry = app(NavKindRegistry::class);

    // `/` is not a Key character, so these are RelativeUriKeys. If the grammar ever rewrote them the
    // round-trip would break silently: `kind` goes to the client and comes back.
    // all() renders the WIRE spelling — what a client sends and what this method has always returned.
    expect(array_keys($registry->all()))->toBe(['nav/link', 'nav/invokable-item']);

    // keys() is the kernel's read and is root-stamped and dotted. Both are asserted because the pair is
    // exactly where a lossy translation would hide: one of them looking right is not enough.
    expect(array_map('strval', $registry->keys()))
        ->toBe(['data-nav.kinds.nav.link', 'data-nav.kinds.nav.invokable-item']);
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

    $output = app(NavInvocableRegistry::class)->invoke('data-nav.resolve', [
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

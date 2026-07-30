<?php

use Illuminate\Http\Request;
use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavGateStage;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\DataNav\InvokableNavItem;
use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavNode;
use Rushing\DataNav\NavRegistry;

// Ticket 03: the keyed registry of named navigations. build() runs
// gate/omit → expand → gate contributed children → stamp, reusing the existing
// walk and adding only the omit phase.

/** A stage that denies any node whose meta['deny'] is truthy. */
function denyByMeta(): NavGateStage
{
    return new class implements NavGateStage
    {
        public function allows(NavNode $node, NavContext $context): bool
        {
            return empty($node->meta['deny']);
        }
    };
}

/** A spy expander recording which nodes it was asked to expand. */
function spyExpander(array &$expanded): NavExpander
{
    return new class($expanded) implements NavExpander
    {
        public function __construct(private array &$expanded) {}

        public function expand(NavNode $node): array
        {
            $this->expanded[] = $node->title();

            return $node->children();
        }
    };
}

it('throws a clear error for an unknown key', function () {
    $registry = app(NavRegistry::class);

    expect(fn () => $registry->build('nope', new NavContext))
        ->toThrow(InvalidArgumentException::class, 'Unknown navigation [nope].');
});

it('omits a gated-out node BEFORE expansion — its expander is never invoked', function () {
    $expanded = [];
    $registry = new NavRegistry(
        gate: (new NavGate)->through(denyByMeta()),
        expander: spyExpander($expanded),
        matcher: app(NavMatcher::class),
    );

    $registry->register('main', fn () => [
        NavLink::make(title: 'Visible', href: '/visible'),
        NavLink::make(title: 'Hidden', href: '/hidden')->withMeta(['deny' => true]),
    ]);

    $tree = $registry->build('main', new NavContext);

    // The hidden node is dropped, and — crucially — was never handed to the
    // expander (gate ran before expand).
    expect(collect($tree->items)->pluck('title')->all())->toBe(['Visible'])
        ->and($expanded)->toBe(['Visible'])
        ->and($expanded)->not->toContain('Hidden');
});

it('gates contributed (expanded) children — a denied child at depth is omitted, a sibling survives', function () {
    // An expander that contributes two children, one of which carries deny meta.
    $expander = new class implements NavExpander
    {
        public function expand(NavNode $node): array
        {
            if ($node instanceof InvokableNavItem) {
                return [
                    NavLink::make(title: 'Kept', href: '/kept'),
                    NavLink::make(title: 'Dropped', href: '/dropped')->withMeta(['deny' => true]),
                ];
            }

            return $node->children();
        }
    };

    $registry = new NavRegistry(
        gate: (new NavGate)->through(denyByMeta()),
        expander: $expander,
        matcher: app(NavMatcher::class),
    );

    $registry->register('main', fn () => [
        InvokableNavItem::make(title: 'Section', invocable: 'x', href: '/section'),
    ]);

    $tree = $registry->build('main', new NavContext);
    $section = $tree->items[0];

    expect(collect($section->children())->pluck('title')->all())->toBe(['Kept']);
});

it('stamps active-state over the surviving, expanded tree', function () {
    $expander = new class implements NavExpander
    {
        public function expand(NavNode $node): array
        {
            if ($node instanceof InvokableNavItem) {
                return [NavLink::make(title: 'Leaf', href: '/section/leaf', match: 'section/leaf')];
            }

            return $node->children();
        }
    };

    $registry = new NavRegistry(
        gate: new NavGate,
        expander: $expander,
        matcher: app(NavMatcher::class),
    );

    $registry->register('main', fn () => [
        InvokableNavItem::make(title: 'Section', invocable: 'x', href: '/section', match: 'section*'),
    ]);

    $context = new NavContext(request: Request::create('/section/leaf'));
    $tree = $registry->build('main', $context);
    $section = $tree->items[0];

    expect($section->children()[0]->title())->toBe('Leaf')
        ->and($section->children()[0]->isActive())->toBeTrue()
        ->and($section->isActiveTrail())->toBeTrue();
});

it('resolves multiple keyed navigations independently', function () {
    $registry = app(NavRegistry::class);

    $registry->register('tenant', fn () => [NavLink::make(title: 'Studio', href: '/studio')]);
    $registry->register('admin', fn () => [NavLink::make(title: 'Platform', href: '/admin')]);

    $tenant = $registry->build('tenant', new NavContext);
    $admin = $registry->build('admin', new NavContext);

    expect(collect($tenant->items)->pluck('title')->all())->toBe(['Studio'])
        ->and(collect($admin->items)->pluck('title')->all())->toBe(['Platform']);
});

it('emits a tree whose serialized shape carries no gate meta', function () {
    $registry = app(NavRegistry::class);
    app(NavGate::class)->through(denyByMeta());

    $registry->register('main', fn () => [
        NavLink::make(title: 'Studio', href: '/studio')->withMeta(['entitlement' => ['composition.content']]),
    ]);

    $tree = $registry->build('main', new NavContext);

    expect($tree->toJson())->not->toContain('entitlement')
        ->and($tree->items[0]->toArray())->not->toHaveKey('meta')
        // meta does not survive the build's round-trip
        ->and($tree->items[0]->meta)->toBe([]);
});

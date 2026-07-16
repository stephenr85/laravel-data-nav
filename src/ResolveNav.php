<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;

/**
 * Active-state resolution as a transport-agnostic popcorn capability — the
 * family shape that replaces the old monolithic URL highlighter. Given a
 * serialized tree and the current path, it walks the tree once, **expands** any
 * invocable-backed node (an {@see InvokableNavItem} builds its own children by
 * dispatching its named capability through the shared {@see InvocableRegistry}),
 * then stamps `active` on each matching node and `activeTrail` on every ancestor
 * of an active descendant, using the bound {@see NavMatcher}.
 *
 * Input `{ tree: array, path: string }`; output `{ tree: array }` (expanded +
 * stamped). An invocable that returns children reuses the same output shape as
 * this resolver — `{ items: array }` — and an unknown invocable name degrades to
 * empty children, never an error.
 */
class ResolveNav implements Invocable
{
    public function __construct(
        private NavMatcher $matcher,
        private NavExpander $expander,
    ) {}

    public function name(): string
    {
        return 'data-nav/resolve';
    }

    public function binding(): Binding
    {
        return Binding::Local;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function invoke(array $input): array
    {
        /** @var array<string, mixed> $treeInput */
        $treeInput = is_array($input['tree'] ?? null) ? $input['tree'] : [];
        $path = (string) ($input['path'] ?? '');

        $tree = NavTree::from($treeInput);

        $items = array_map(
            fn (NavNode $node): NavNode => $this->stamp($node, $path),
            $tree->items,
        );

        return ['tree' => (new NavTree($items))->toArray()];
    }

    /**
     * Stamp one node and its subtree bottom-up: a node is on the active trail
     * when any descendant resolves active (or is itself on the trail).
     */
    private function stamp(NavNode $node, string $path): NavNode
    {
        $children = array_map(
            fn (NavNode $child): NavNode => $this->stamp($child, $path),
            $this->expander->expand($node),
        );

        $active = $this->matcher->matches($node, $path);

        $activeTrail = false;
        foreach ($children as $child) {
            if ($child->isActive() || $child->isActiveTrail()) {
                $activeTrail = true;
                break;
            }
        }

        return $node->stamped($active, $activeTrail, $children);
    }
}

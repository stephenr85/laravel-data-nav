<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;
use Rushing\Popcorn\InvocableRegistry;

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
 *
 * Infrastructure, so `final`.
 */
final class ResolveNav implements Invocable
{
    public function __construct(
        private readonly NavMatcher $matcher,
        private readonly InvocableRegistry $registry,
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
            $this->childrenOf($node, $path),
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

    /**
     * The children to walk. A static node yields its held children; an
     * invocable-backed node builds its children on demand by dispatching its
     * capability. Expansion is recursive by construction — an expanded child may
     * itself be invocable-backed and is walked in turn.
     *
     * @return array<int, NavNode>
     */
    private function childrenOf(NavNode $node, string $path): array
    {
        if ($node instanceof InvokableNavItem) {
            return $this->expand($node);
        }

        return $node->children();
    }

    /**
     * Dispatch the node's capability and hydrate the returned nodes. An
     * unregistered name degrades to empty children (safe), never an error.
     *
     * @return array<int, NavNode>
     */
    private function expand(InvokableNavItem $node): array
    {
        if (! $this->registry->has($node->invocable)) {
            return [];
        }

        $output = $this->registry->invoke($node->invocable, $node->input);

        $items = is_array($output['items'] ?? null) ? $output['items'] : [];

        return array_values(array_map(
            fn (mixed $item): NavNode => $item instanceof NavNode ? $item : NavNode::from($item),
            $items,
        ));
    }
}

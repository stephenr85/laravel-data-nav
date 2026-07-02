<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\Binding;
use Rushing\Popcorn\Contracts\Invocable;

/**
 * Active-state resolution as a transport-agnostic popcorn capability — the
 * family shape that replaces the old monolithic URL highlighter. Given a
 * serialized tree and the current path, it walks the tree once and stamps
 * `active` on each matching node and `activeTrail` on every ancestor of an
 * active descendant, using the bound {@see NavMatcher}.
 *
 * Input `{ tree: array, path: string }`; output `{ tree: array }` (stamped).
 * Invocable-backed subtree *expansion* is layered on in a later slice; this one
 * only stamps.
 *
 * Infrastructure, so `final`.
 */
final class ResolveNav implements Invocable
{
    public function __construct(
        private readonly NavMatcher $matcher,
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
     * The children to walk. Static nodes yield their held children; a later
     * slice overrides this seam to expand invocable-backed nodes.
     *
     * @return array<int, NavNode>
     */
    private function childrenOf(NavNode $node, string $path): array
    {
        return $node->children();
    }
}

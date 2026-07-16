<?php

namespace Rushing\DataNav\Contracts;

use Rushing\DataNav\NavNode;

/**
 * The pluggable subtree-expansion strategy: given a node during resolution,
 * yield the children to walk. The seam a host swaps to teach {@see ResolveNav}
 * how a custom node kind builds its subtree — previously resolution hard-coded
 * an `instanceof InvokableNavItem` branch, so kinds were data-extensible but not
 * behavior-extensible.
 *
 * The default {@see InvokableNavExpander} preserves the original behavior: an
 * {@see InvokableNavItem} dispatches its named popcorn capability; any other
 * node yields its eagerly-held children.
 */
interface NavExpander
{
    /**
     * The children of the given node to walk during resolution.
     *
     * @return array<int, NavNode>
     */
    public function expand(NavNode $node): array;
}

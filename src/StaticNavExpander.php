<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;

/**
 * The no-op expansion strategy: every node yields whatever children it already
 * holds, never invoking a capability. It is the expander {@see NavRegistry} uses
 * for its *stamping* pass — by then the tree has already been gated and expanded
 * once (with live {@see NavContext}), so re-invoking an
 * {@see InvokableNavItem}'s capability would be wasteful and, worse, would bring
 * back children the gate already omitted. Reusing {@see ResolveNav} with this
 * expander stamps active-state over the surviving tree without disturbing it.
 */
class StaticNavExpander implements NavExpander
{
    /**
     * @return array<int, NavNode>
     */
    public function expand(NavNode $node): array
    {
        return $node->children();
    }
}

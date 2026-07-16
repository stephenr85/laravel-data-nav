<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\Popcorn\InvocableRegistry;

/**
 * The default {@see NavExpander} — the behavior that used to live inline in
 * {@see ResolveNav}. An {@see InvokableNavItem} builds its children on demand by
 * dispatching its named popcorn capability through the shared
 * {@see InvocableRegistry}; an unregistered name degrades to empty children
 * (safe), never an error. Any other node yields its eagerly-held children.
 */
class InvokableNavExpander implements NavExpander
{
    public function __construct(
        private InvocableRegistry $registry,
    ) {}

    /**
     * @return array<int, NavNode>
     */
    public function expand(NavNode $node): array
    {
        if (! $node instanceof InvokableNavItem) {
            return $node->children();
        }

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

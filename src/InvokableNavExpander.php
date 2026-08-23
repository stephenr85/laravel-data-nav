<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavExpander;
use Rushing\Popcorn\InvocableRegistry;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;

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

        // `invocable` arrives from a hydrated tree, i.e. it is a NAME and not a key — a host's JSON
        // can carry a typo that is not merely unregistered but unparseable. `has()` is a contract
        // method and throws on an illegal key by design (popcorn ticket 30 D4); `invoke()` is the
        // name-taking door that reports an illegal name as an absent one. Going through the miss is
        // what keeps this expander's promise that an unresolvable name degrades rather than errors.
        try {
            $output = $this->registry->invoke($node->invocable, $node->input);
        } catch (RegistryMiss) {
            return [];
        }

        $items = is_array($output['items'] ?? null) ? $output['items'] : [];

        return array_values(array_map(
            fn (mixed $item): NavNode => $item instanceof NavNode ? $item : NavNode::from($item),
            $items,
        ));
    }
}

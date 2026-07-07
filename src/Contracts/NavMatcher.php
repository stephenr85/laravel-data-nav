<?php

namespace Rushing\DataNav\Contracts;

/**
 * The pluggable active-state strategy: does this node match the current path?
 * The seam a host swaps to change how active-state is decided (glob, exact,
 * locale-aware, query-aware) without touching resolution.
 */
interface NavMatcher
{
    public function matches(NavItem $item, string $path): bool;
}

<?php

declare(strict_types=1);

namespace Rushing\DataNav\Contracts;

use Rushing\DataNav\InvokableNavItem;
use Rushing\DataNav\NavLink;

/**
 * The nav-node contract. One navigation node — a title, an optional href, an
 * optional match pattern, children (themselves NavItems), and the resolved
 * active / activeTrail flags.
 *
 * This is an interface, not a class, so heterogeneous node kinds — a static
 * {@see NavLink} and an invocable-backed
 * {@see InvokableNavItem} that builds its own subtree — can
 * share one contract and serialize into a single polymorphic tree.
 */
interface NavItem
{
    public function title(): string;

    public function href(): ?string;

    /**
     * A Laravel `Request::is()` glob (e.g. `blog/*`) driving active-state; null
     * means fall back to an exact href match.
     */
    public function matchPattern(): ?string;

    /**
     * @return array<int, NavItem>
     */
    public function children(): array;

    public function isActive(): bool;

    public function isActiveTrail(): bool;
}

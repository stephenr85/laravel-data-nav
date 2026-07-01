<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * An ordered collection of NavItems forming one navigation region (a sidebar,
 * a primary menu, a Topics submenu). Hosts compose a full nav from several
 * sources — chrome items plus contributed subtrees. Serializes to JSON
 * (Inertia) or array (Blade).
 */
final class NavTree extends Data
{
    /**
     * @param  array<int, NavItem>  $items
     */
    public function __construct(
        #[DataCollectionOf(NavItem::class)]
        public array $items = [],
    ) {}

    /**
     * Construct from an ordered list of NavItems.
     *
     * @param  array<int, NavItem>  $items
     */
    public static function make(array $items = []): self
    {
        return new self(items: array_values($items));
    }
}

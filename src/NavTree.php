<?php

namespace Rushing\DataNav;

use Rushing\LaravelDataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An ordered collection of nav nodes forming one navigation region (a sidebar,
 * a primary menu, a Topics submenu). Items are a polymorphic {@see NavNode}
 * union — static {@see NavLink}s and invocable-backed {@see InvokableNavItem}s
 * hydrate into their concrete kind via the node `kind` discriminator.
 *
 * Serializes to JSON (Inertia) or array (Blade) with nested children preserved.
 * A versioned schema (`nav/tree`, v1) via {@see SchemaIdentity}. Not `final`.
 */
#[TypeScript]
class NavTree extends Data implements SchemaIdentity
{
    /**
     * @param  array<int, NavNode>  $items
     */
    public function __construct(
        #[DataCollectionOf(NavNode::class)]
        public array $items = [],
    ) {}

    /**
     * Construct from an ordered list of nav nodes.
     *
     * @param  array<int, NavNode>  $items
     */
    public static function make(array $items = []): self
    {
        return new self(items: array_values($items));
    }

    public static function schemaName(): string
    {
        return 'nav/tree';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}

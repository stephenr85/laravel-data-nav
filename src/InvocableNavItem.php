<?php

namespace Rushing\DataNav;

use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The dynamic nav node that knows what to invoke — a node which declares a
 * subtree it does not eagerly hold. It carries the name of a registered
 * laravel-popcorn invocable (plus optional input); resolution dispatches that
 * capability to build the node's children on demand. This is the mechanism
 * behind a self-building menu (e.g. a Topics submenu projected from a category
 * tree): the item defers its subtree to a capability, local now, MCP/webhook
 * later — transport-agnostic by construction.
 *
 * A versioned schema (`nav/invocable-item`, v1) via {@see SchemaIdentity}, so a
 * mixed tree of {@see NavLink} and this kind is a discriminable union.
 *
 * ## Declaring a constructor here means re-declaring every serialized base field
 *
 * {@see NavLink} declares none and inherits {@see NavNode}'s, so it hydrates every base field for
 * free. This class declares one in order to add `invocable`/`input` — and spatie hydrates a Data
 * object THROUGH its constructor, so any base field this signature omits is silently reset to its
 * default on the way back in, no matter what the array carried.
 *
 * `locked` was that field. It is wire-visible by design (unlike the `#[Hidden]` meta bag), it round-
 * tripped correctly for a `NavLink`, and it vanished for an invocable node — which is every
 * package-contributed nav SEAT, and therefore every node a soft gate would ever lock. The loss
 * happened inside `NavRegistry::build()`'s own `toArray()`/`from()` active-stamping round-trip, so a
 * lock could be produced correctly at projection and still never reach a client.
 *
 * A new serialized field on `NavNode` must be added to this signature too. `meta` is the deliberate
 * exception: it is `#[Hidden]`, so the wire never carries it and there is nothing to hydrate.
 */
#[TypeScript]
class InvocableNavItem extends NavNode implements SchemaIdentity
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, NavNode>  $children
     */
    public function __construct(
        string $title,
        public string $invocable,
        public array $input = [],
        ?string $href = null,
        ?string $match = null,
        #[DataCollectionOf(NavNode::class)]
        array $children = [],
        bool $active = false,
        bool $activeTrail = false,
        ?string $icon = null,
        ?string $routeName = null,
        ?NavLocked $locked = null,
        ?string $zone = null,
    ) {
        parent::__construct(
            kind: 'nav/invocable-item',
            title: $title,
            href: $href,
            match: $match,
            children: $children,
            active: $active,
            activeTrail: $activeTrail,
            icon: $icon,
            routeName: $routeName,
            locked: $locked,
            zone: $zone,
        );
    }

    /**
     * Ergonomic factory: a node pointing at a registered capability by name.
     *
     * @param  array<string, mixed>  $input
     */
    public static function make(
        string $title,
        string $invocable,
        array $input = [],
        ?string $href = null,
        ?string $match = null,
        ?string $icon = null,
        ?string $routeName = null,
    ): self {
        return new self(
            title: $title,
            invocable: $invocable,
            input: $input,
            href: $href,
            match: $match,
            icon: $icon,
            routeName: $routeName,
        );
    }

    public static function schemaName(): string
    {
        return 'nav/invocable-item';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}

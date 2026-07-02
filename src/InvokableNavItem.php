<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Rushing\LaravelDataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Attributes\DataCollectionOf;

/**
 * The dynamic nav node that knows what to invoke — a node which declares a
 * subtree it does not eagerly hold. It carries the name of a registered
 * laravel-popcorn invocable (plus optional input); resolution dispatches that
 * capability to build the node's children on demand. This is the mechanism
 * behind a self-building menu (e.g. a Topics submenu projected from a category
 * tree): the item defers its subtree to a capability, local now, MCP/webhook
 * later — transport-agnostic by construction.
 *
 * A versioned schema (`nav/invokable-item`, v1) via {@see SchemaIdentity}, so a
 * mixed tree of {@see NavLink} and this kind is a discriminable union.
 */
class InvokableNavItem extends NavNode implements SchemaIdentity
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
    ) {
        parent::__construct(
            kind: 'nav/invokable-item',
            title: $title,
            href: $href,
            match: $match,
            children: $children,
            active: $active,
            activeTrail: $activeTrail,
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
    ): self {
        return new self(
            title: $title,
            invocable: $invocable,
            input: $input,
            href: $href,
            match: $match,
        );
    }

    public static function schemaName(): string
    {
        return 'nav/invokable-item';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}

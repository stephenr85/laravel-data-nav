<?php

namespace Rushing\DataNav;

use Rushing\LaravelDataSchemas\Contracts\SchemaIdentity;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The static nav node — the common case: a titled link with an optional match
 * pattern and eagerly-held children. Its subtree is whatever was provided; it
 * does not build itself (that is {@see InvokableNavItem}).
 *
 * A versioned, absolutely-addressable schema (`nav/link`, v1) via
 * {@see SchemaIdentity}, so it joins the family's Data → JSON Schema → TS
 * pipeline.
 */
#[TypeScript]
class NavLink extends NavNode implements SchemaIdentity
{
    /**
     * Ergonomic factory so a host can build links cheaply without naming the
     * `kind` discriminator.
     *
     * @param  array<int, NavNode>  $children
     */
    public static function make(
        string $title,
        ?string $href = null,
        ?string $match = null,
        array $children = [],
    ): self {
        return new self(
            kind: 'nav/link',
            title: $title,
            href: $href,
            match: $match,
            children: $children,
        );
    }

    public static function schemaName(): string
    {
        return 'nav/link';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}

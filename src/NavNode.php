<?php

namespace Rushing\DataNav;

use Illuminate\Container\Container;
use Rushing\DataNav\Contracts\NavItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The abstract Data base every nav node extends. It holds the shared node shape
 * — a `kind` discriminator plus title / href / match / children / active /
 * activeTrail — and implements the {@see NavItem} contract over those fields.
 *
 * Because it implements spatie's {@see PropertyMorphableData}, a property or
 * collection typed as `NavNode` hydrates each element into its concrete kind via
 * {@see morph()} — the seam that lets a `NavTree` carry a discriminable union of
 * {@see NavLink} and {@see InvokableNavItem} nodes. The `kind` value is the
 * on-the-wire discriminator; each concrete kind also carries its own schema
 * `$id` (via {@see SchemaIdentity}).
 */
#[TypeScript]
abstract class NavNode extends Data implements NavItem, PropertyMorphableData
{
    /**
     * @param  array<int, NavNode>  $children
     */
    public function __construct(
        #[PropertyForMorph]
        public string $kind,
        public string $title,
        public ?string $href = null,
        public ?string $match = null,
        #[DataCollectionOf(NavNode::class)]
        public array $children = [],
        public bool $active = false,
        public bool $activeTrail = false,
        /**
         * A decorative icon name (e.g. `'Library'`), not a component — the host
         * renderer maps the name to its own icon. A universal optional field, not
         * a new kind. Nullable so existing nodes are unaffected.
         */
        public ?string $icon = null,
        /**
         * The stable route identity a host binds this node to (the join key
         * across a route table, the nav, and a client route registry). Nullable;
         * active-state may match on it instead of a path glob.
         */
        public ?string $routeName = null,
    ) {}

    /**
     * Map the `kind` discriminator to the concrete node class through the shared
     * {@see NavKindRegistry}, so host-registered kinds are hydration-safe. An
     * unknown kind returns null, letting spatie fall back to its default
     * hydration (unchanged behavior). Resolved through the container so the
     * static morph seam can reach the bound registry; degrades to the two
     * built-in kinds when the container is unavailable (e.g. bare unit use).
     *
     * @param  array<string, mixed>  $properties
     */
    public static function morph(array $properties): ?string
    {
        $kind = $properties['kind'] ?? null;
        $kind = is_string($kind) ? $kind : null;

        $container = Container::getInstance();

        if ($container->bound(NavKindRegistry::class)) {
            return $container->make(NavKindRegistry::class)->resolve($kind);
        }

        return match ($kind) {
            'nav/link' => NavLink::class,
            'nav/invokable-item' => InvokableNavItem::class,
            default => null,
        };
    }

    public function title(): string
    {
        return $this->title;
    }

    public function href(): ?string
    {
        return $this->href;
    }

    public function matchPattern(): ?string
    {
        return $this->match;
    }

    /**
     * @return array<int, NavItem>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isActiveTrail(): bool
    {
        return $this->activeTrail;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * A copy of this node with active-state and children replaced, preserving
     * the concrete node kind (and any extra fields, e.g. an invocable name) via
     * clone. Used by resolution to stamp a fresh, immutable-ish tree.
     *
     * @param  array<int, NavNode>  $children
     */
    public function stamped(bool $active, bool $activeTrail, array $children): static
    {
        $clone = clone $this;
        $clone->active = $active;
        $clone->activeTrail = $activeTrail;
        $clone->children = $children;

        return $clone;
    }
}

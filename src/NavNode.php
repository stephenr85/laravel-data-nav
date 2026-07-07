<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavItem;
use Rushing\LaravelDataSchemas\Contracts\SchemaIdentity;
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
    ) {}

    /**
     * Map the `kind` discriminator to the concrete node class. An unknown kind
     * returns null, letting spatie fall back to its default hydration.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function morph(array $properties): ?string
    {
        return match ($properties['kind'] ?? null) {
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

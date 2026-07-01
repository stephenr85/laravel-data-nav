<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * One node of navigation — a title, an href, an optional match pattern,
 * children, and the resolved active / activeTrail flags. Framework-agnostic;
 * serializes to JSON (Inertia) or array (Blade) via spatie/laravel-data.
 *
 * The `match` pattern, when present, is evaluated with Laravel's
 * `Request::is()` glob semantics (e.g. `blog/*`). When absent, active-state
 * falls back to an exact path match against `href`.
 */
final class NavItem extends Data
{
    /**
     * @param  array<int, NavItem>  $children
     */
    public function __construct(
        public string $title,
        public string $href,
        public ?string $match = null,
        #[DataCollectionOf(NavItem::class)]
        public array $children = [],
        public bool $active = false,
        public bool $activeTrail = false,
    ) {}

    /**
     * Ergonomic factory so a host can build items cheaply.
     *
     * @param  array<int, NavItem>  $children
     */
    public static function make(
        string $title,
        string $href,
        ?string $match = null,
        array $children = [],
    ): self {
        return new self(
            title: $title,
            href: $href,
            match: $match,
            children: $children,
        );
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * Return a copy of this item with the active-state flags stamped, leaving
     * the receiver untouched. Children are replaced wholesale by the resolver.
     *
     * @param  array<int, NavItem>|null  $children
     */
    public function withState(bool $active, bool $activeTrail, ?array $children = null): self
    {
        return new self(
            title: $this->title,
            href: $this->href,
            match: $this->match,
            children: $children ?? $this->children,
            active: $active,
            activeTrail: $activeTrail,
        );
    }
}

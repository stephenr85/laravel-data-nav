<?php

namespace Rushing\DataNav;

/**
 * The open registry mapping a node `kind` discriminator to its concrete
 * {@see NavNode} class. Pre-seeded with the two package built-ins
 * (`nav/link` → {@see NavLink}, `nav/invokable-item` → {@see InvokableNavItem});
 * a host registers additional kinds so its custom nodes survive the
 * `toArray()` → invoke → `from()` round-trip in {@see ResolveNav}.
 *
 * Before this registry, {@see NavNode::morph()} was a closed static `match`: an
 * unregistered `kind` returned null and spatie fell back to hydrating the
 * abstract base — silently dropping the custom node's fields (or erroring, since
 * {@see NavNode} is abstract). The registry makes host kinds hydration-safe
 * without touching the package.
 */
class NavKindRegistry
{
    /**
     * @var array<string, class-string<NavNode>>
     */
    private array $kinds = [
        'nav/link' => NavLink::class,
        'nav/invokable-item' => InvokableNavItem::class,
    ];

    /**
     * Register (or override) a concrete node class for a `kind` discriminator.
     *
     * @param  class-string<NavNode>  $class
     */
    public function register(string $kind, string $class): static
    {
        $this->kinds[$kind] = $class;

        return $this;
    }

    /**
     * Resolve a `kind` to its concrete node class, or null when unregistered
     * (spatie then falls back to its default hydration, as before).
     *
     * @return class-string<NavNode>|null
     */
    public function resolve(?string $kind): ?string
    {
        return $kind === null ? null : ($this->kinds[$kind] ?? null);
    }

    /**
     * @return array<string, class-string<NavNode>>
     */
    public function all(): array
    {
        return $this->kinds;
    }
}

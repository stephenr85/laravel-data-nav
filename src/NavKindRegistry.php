<?php

namespace Rushing\DataNav;

use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;
use Rushing\Popcorn\Registries\RelativeUriKey;

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
 *
 * ## Declared, as of registry-kernel's outstanding-12 burn-down
 *
 * The package's third declared registry, beside {@see NavRegistry} (`data-nav.navigations`) and
 * {@see NavInvocableRegistry} (`data-nav`). Until it declared, `popcorn:registries` listed two of this
 * package's three keyspaces and an agent looking for where to register a node kind would find neither
 * the branch nor its two built-in occupants.
 *
 * ⚠️ **Keys carry a slash, which is why they are {@see RelativeUriKey}s.** `nav/link` is not merely an
 * internal key — it is the `kind` discriminator that ships to clients and comes back on the round-trip,
 * so it cannot be rewritten to suit a key grammar. `/` is not a `Key` character, so a bare string throws;
 * `RelativeUriKey` preserves the coordinate as spelled, losslessly, which is the case
 * {@see RelativeUriKey}'s own docblock cites this class for.
 *
 * ⚠️ **`resolve()` changed meaning; the old meaning was renamed to {@see classFor()}.** Both answer
 * "what is under this key", but they disagree on the miss: the kernel's {@see Registry::resolve()}
 * THROWS, while this class's returned `null` — and that null is load-bearing, because
 * {@see NavNode::morph()} hands it straight to spatie, for which null means *fall back to default
 * hydration*. A silent behaviour swap on a same-named method is exactly the failure a rename prevents.
 * `classFor()` also keeps the nullable INPUT, which the kernel's key contract does not admit.
 */
#[IsRegistry(
    root: 'data-nav.kinds',
    of: 'nav node kinds — one concrete NavNode class per `kind` discriminator, so a host node survives the round-trip',
    arity: RegistryArity::PickOne,
    entryType: NavNode::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'Supersede records the behaviour this class always had — registration was a plain array '
        .'assignment, and the docblock calls overriding a built-in kind a supported act ("register (or '
        .'override)"). The two package built-ins seed at construction and a host may replace either.',
)]
/**
 * @implements Registry<class-string<NavNode>>
 */
class NavKindRegistry implements Registry
{
    /**
     * The kinds this package ships, seeded at construction so a host that registers nothing still
     * round-trips its own nodes.
     *
     * @var array<string, class-string<NavNode>>
     */
    public const BUILT_INS = [
        'nav/link' => NavLink::class,
        'nav/invokable-item' => InvokableNavItem::class,
    ];

    /** @var BasicRegistry<class-string<NavNode>> */
    private BasicRegistry $kinds;

    public function __construct()
    {
        $this->kinds = BasicRegistry::for($this);

        foreach (self::BUILT_INS as $kind => $class) {
            $this->kinds->register(RelativeUriKey::of($kind), $class, by: self::class);
        }
    }

    /**
     * Register (or override) a concrete node class for a `kind` discriminator.
     *
     * The kernel's signature. Source-compatible with every existing two-argument call — the entry widens
     * to `mixed` and the return type is unchanged at `static`.
     *
     * @param  class-string<NavNode>  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->kinds->register($key instanceof RegistryKey ? $key : RelativeUriKey::of($key), $entry, $by, $ability);

        return $this;
    }

    /**
     * Resolve a `kind` to its concrete node class, or null when unregistered — spatie then falls back to
     * its default hydration, as before.
     *
     * This is what {@see resolve()} used to be, and the null return is the contract
     * {@see NavNode::morph()} depends on. A null `$kind` is admitted for the same reason: morph() is
     * handed whatever was on the wire.
     *
     * @return class-string<NavNode>|null
     */
    public function classFor(?string $kind): ?string
    {
        return $kind === null ? null : $this->kinds->tryResolve(RelativeUriKey::of($kind));
    }

    /**
     * Every registered kind, keyed by the `kind` discriminator **as it ships on the wire**.
     *
     * ⚠️ Not `(string) $key`. A stored key is root-stamped and dotted — `data-nav.kinds.nav.link` — so
     * rendering it directly returns neither the spelling a client sends nor the one this method has
     * always returned. The way back is the pair {@see RelativeUriKey} documents as lossless in both
     * directions: strip the root segments, then {@see RelativeUriKey::fromSegments()}. This was caught
     * by a test asserting the exact array, not by the type checker — the broken version returns
     * `array<string, class-string>` too.
     *
     * @return array<string, class-string<NavNode>>
     */
    public function all(): array
    {
        $depth = count(IsRegistry::of($this)->rootKey()->segments());
        $kinds = [];

        foreach ($this->kinds->keys() as $key) {
            $relative = RelativeUriKey::fromSegments(array_slice($key->segments(), $depth));
            $kinds[(string) $relative] = $this->kinds->resolve($key);
        }

        return $kinds;
    }

    /* ---------------- Registry contract ---------------- */

    public function has(RegistryKey|string $key): bool
    {
        return $this->kinds->has($key instanceof RegistryKey ? $key : RelativeUriKey::of($key));
    }

    /** ⚠️ THROWS on a miss, unlike {@see classFor()}. See the class docblock. */
    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->kinds->resolve($key instanceof RegistryKey ? $key : RelativeUriKey::of($key));
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->kinds->tryResolve($key instanceof RegistryKey ? $key : RelativeUriKey::of($key));
    }

    /** @return list<class-string<NavNode>> */
    public function matches(RegistryKey|string $key): array
    {
        return $this->kinds->matches($key instanceof RegistryKey ? $key : RelativeUriKey::of($key));
    }

    /** @return list<RegistryKey> */
    public function keys(): array
    {
        return $this->kinds->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->kinds->unfiltered();
    }
}

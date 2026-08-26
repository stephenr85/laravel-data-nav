<?php

namespace Rushing\DataNav;

use Illuminate\Container\Container;
use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * The keyed registry of named navigations — the replacement for a host's
 * hardcoded realm branch. A host registers named navigation factories
 * (`register('tenant', fn () => [...nodes])`) and asks the registry to build one
 * against a {@see NavContext} (`build('admin', $ctx)`); a second navigation
 * region (settings, footer, mobile) is then just another key.
 *
 * `build()` runs the full pipeline, reusing the package's existing seams and
 * adding only the omit phase:
 *
 *   1. **gate / omit** — drop any node the {@see NavGate} denies (secure-by-
 *      omission), **before** its children are expanded, so a hidden node never
 *      pays to build its subtree (its expander is never invoked).
 *   2. **expand** — via the bound {@see NavExpander} (an {@see InvokableNavItem}
 *      yields its dynamic children). The current {@see NavContext} is bound into
 *      the container for the duration so a host expander/capability can resolve
 *      it (its `expand()` contract carries no context param).
 *   3. **gate contributed children** — the same gate runs over the expanded
 *      children, so gating is uniform at any depth.
 *   4. **stamp active-state** — via the existing {@see ResolveNav}, driven with a
 *      {@see StaticNavExpander} so the already-expanded tree is stamped without
 *      being re-expanded (which would undo the gating). The round-trip through
 *      `toArray()`/`from()` also strips the server-only gate-meta, so the built
 *      tree is leak-free.
 *
 * The registry names no host vocabulary — the policy lives entirely in the
 * injected {@see NavGate} stages and the host's expanders.
 *
 * ## It owns a branch of `data-nav`, beside the capabilities
 *
 * Registry-kernel ticket 38 cut the private `[key => factory]` array onto the kernel's
 * {@see BasicRegistry}. The root nests under `data-nav`, which {@see NavInvocableRegistry} owns —
 * legal, and the same relationship `composition.handlers` has with `composition`: the two answer
 * different questions about the same subject (*which navigations exist* vs *the invocables that
 * resolve and expand them*). Longest-prefix routing separates them with no kernel change.
 *
 * Only `build()`'s miss changed shape: an unknown key threw a package-local
 * `InvalidArgumentException` and now throws the kernel's {@see RegistryMiss}.
 */
#[IsRegistry(
    root: 'data-nav.navigations',
    of: 'named navigations — one factory per navigation region (tenant, operator, docs, …)',
    arity: RegistryArity::PickOne,
    entryType: 'callable(NavContext): list<NavNode>',
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'A host re-registering an existing name deliberately overrides that navigation; it is the '
        .'documented override seam, not an accident. Supersession APPENDS, so an override moves the '
        .'name to the end of registration order — harmless here, because nothing enumerates across '
        .'navigations (each is built by name).',
)]
class NavRegistry implements Gated, Registry
{
    private BasicRegistry $entries;

    public function __construct(
        private NavGate $gate,
        private NavExpander $expander,
        private NavMatcher $matcher,
    ) {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register (or override) a named navigation factory. The factory yields the
     * raw, unexpanded node tree; it may read the {@see NavContext} it is given.
     *
     * Widened from the contract rather than shadowing it — contravariance, so every historical
     * `register('tenant', fn () => [...])` caller keeps working unchanged.
     *
     * @param  callable(NavContext): array<int, NavNode>|mixed  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    /**
     * The registered navigation names, as callers spelled them — {@see keys()} with the declared
     * root stripped back off, because keys go relative in and absolute out (ticket 20 D2).
     *
     * @return string[]
     */
    public function names(): array
    {
        return $this->entries->relativeKeys();
    }

    /**
     * @return list<RegistryKey>
     */
    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * Build one named navigation against a context: gate/omit → expand → gate
     * contributed children → stamp active-state. Returns a fully-resolved,
     * leak-free {@see NavTree}. An unknown key is a clear error.
     *
     * @throws RegistryMiss no navigation is registered under that name
     */
    public function build(RegistryKey|string $key, NavContext $context): NavTree
    {
        /** @var callable(NavContext): array<int, NavNode> $factory */
        $factory = $this->resolve($key);

        // Thread the context to expansion: the NavExpander contract carries no
        // context, so a host capability resolves the current NavContext from the
        // container (bound here for the length of the build).
        Container::getInstance()->instance(NavContext::class, $context);

        $raw = $factory($context);
        $tree = NavTree::make($this->gateExpand($raw, $context));

        // Stamp active-state over the surviving, already-expanded tree — reuse
        // ResolveNav, but with a static expander so it never re-expands (which
        // would re-invoke capabilities and undo child-level gating). The
        // toArray()/from() round-trip also strips the #[Hidden] gate-meta.
        $resolve = new ResolveNav($this->matcher, new StaticNavExpander);

        $output = $resolve->invoke([
            'tree' => $tree->toArray(),
            'path' => $context->request?->path() ?? '',
        ]);

        return NavTree::from($output['tree']);
    }

    /**
     * Recursively drop gated-out nodes (before expanding them), expand the
     * survivors via the bound expander, and gate the contributed children the
     * same way — so a denied node's expander is never invoked and gating is
     * uniform at any depth. Active flags are left false here; the stamp pass
     * sets them.
     *
     * @param  array<int, NavNode>  $nodes
     * @return array<int, NavNode>
     */
    private function gateExpand(array $nodes, NavContext $context): array
    {
        $survivors = [];

        foreach ($nodes as $node) {
            if (! $node instanceof NavNode) {
                continue;
            }

            if (! $this->gate->allows($node, $context)) {
                continue; // omit BEFORE expand — the node's expander never runs
            }

            $children = $this->gateExpand($this->expander->expand($node), $context);
            $survivors[] = $node->stamped(active: false, activeTrail: false, children: $children);
        }

        return $survivors;
    }
}

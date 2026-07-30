<?php

namespace Rushing\DataNav;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;

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
 */
class NavRegistry
{
    /**
     * @var array<string, callable(NavContext): array<int, NavNode>>
     */
    private array $factories = [];

    public function __construct(
        private NavGate $gate,
        private NavExpander $expander,
        private NavMatcher $matcher,
    ) {}

    /**
     * Register (or override) a named navigation factory. The factory yields the
     * raw, unexpanded node tree; it may read the {@see NavContext} it is given.
     *
     * @param  callable(NavContext): array<int, NavNode>  $factory
     */
    public function register(string $key, callable $factory): static
    {
        $this->factories[$key] = $factory;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->factories);
    }

    /**
     * Build one named navigation against a context: gate/omit → expand → gate
     * contributed children → stamp active-state. Returns a fully-resolved,
     * leak-free {@see NavTree}. An unknown key is a clear error.
     */
    public function build(string $key, NavContext $context): NavTree
    {
        if (! isset($this->factories[$key])) {
            throw new InvalidArgumentException("Unknown navigation [{$key}].");
        }

        // Thread the context to expansion: the NavExpander contract carries no
        // context, so a host capability resolves the current NavContext from the
        // container (bound here for the length of the build).
        Container::getInstance()->instance(NavContext::class, $context);

        $raw = ($this->factories[$key])($context);
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

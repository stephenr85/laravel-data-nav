# laravel-data-nav — Context

A domain-agnostic navigation spine: a serializable, polymorphic nav tree with server-resolved
active-state, consumable by both Inertia/React (JSON) and Blade (array) hosts. It emits no HTML —
rendering is the host's job. Part of the `laravel-data-*` family (`laravel-data-schemas`,
`laravel-data-filters`), so its DTOs are `SchemaIdentity` Data classes (JSON Schema / TS parity),
and resolution is a `laravel-popcorn` invocable rather than a bespoke service.

## Language

**NavItem**:
The nav-node _contract_ (an interface) — a `title`, an optional `href`, an optional `match`
pattern, `children[]` (themselves `NavItem`s), and the resolved `active` / `activeTrail` flags.
Heterogeneous node kinds share it so one tree serializes as a discriminable polymorphic union.
_Avoid_: MenuLink, MenuItem.

**NavLink**:
The static node kind — the common case: a titled link with an optional match pattern and
eagerly-held children (schema `nav/link`).
_Avoid_: MenuLink.

**InvokableNavItem**:
The dynamic node kind that _knows what to invoke_ — it declares a subtree it does not eagerly
hold, carrying a registered popcorn invocable name (+ optional input); resolution dispatches that
capability to build its children on demand (schema `nav/invokable-item`). The mechanism behind a
self-building menu (e.g. a Topics submenu projected from a category tree).
_Avoid_: LazyNavItem, DynamicMenu.

**NavTree**:
An ordered collection of nav nodes forming one navigation region (a sidebar, a primary menu, a
Topics submenu). Hosts compose a full nav from several sources — chrome nodes plus contributed
subtrees (schema `nav/tree`).
_Avoid_: Menu, Navbar.

**ResolveNav**:
The `data-nav/resolve` popcorn invocable (Local binding) that walks a `NavTree` once — **expands**
each invocable-backed node via the shared `InvocableRegistry`, then **stamps** each node's `active`
and `activeTrail` server-side against the current path — the canonical active-state answer.
Clients prefer the stamped flags; client URL-matching is only a fallback. `Nav::resolve()` is the
thin request adapter over it.
_Avoid_: NavResolver (removed), ActiveMatcher, current-menu.

**NavMatcher**:
The pluggable active-state strategy (`matches(NavItem, path): bool`); the default `PathNavMatcher`
activates a node by its `match` glob, else an exact `href`. The interface is the seam a host swaps
to change how active-state is decided.
_Avoid_: Highlighter.

**Active trail**:
The chain of ancestor nodes leading to the active leaf — what a parent section highlights on even
when the active item is a descendant.
_Avoid_: breadcrumb (that's a separate ordered path), open path.

**NavRegistry**:
The keyed registry of named navigations — `register(key, factory)` / `build(key, NavContext)`. A host
registers named nav factories (`'tenant'`, `'admin'`, a footer, a mobile menu) and asks the registry to
build one against a context; a realm/region is just a key. `build()` runs one pipeline — **gate/omit →
expand → gate contributed children → stamp** — reusing the existing walk (`NavExpander` / `ResolveNav`)
and adding only the omit phase: a gated-out node is dropped *before* its children are expanded, so a
hidden node never pays to build its subtree. Stamping reuses `ResolveNav` with a `StaticNavExpander`
(the tree is already expanded once, with live context — it must not be re-expanded). The one host-free
model shift behind it: **there is no "section"** — gating and contribution are properties of *nodes* at
any depth, so contribution reuses the existing `InvokableNavItem` / `NavExpander` seam.
_Avoid_: NavBuilder (it composes existing seams, it is not a monolithic builder), Menu manager.

**NavGate / NavGateStage**:
The injected omit pipeline — the package's *mechanism*, the host's *policy*. `NavGate` holds ordered
`NavGateStage`s (`allows(node, context): bool`), ANDs them, and short-circuits on the first deny;
**bound empty by default** (no stages ⇒ everything visible). The package names no host concept — a stage
reads whatever it needs off the node's opaque gate-**meta** and off `NavContext`. Secure-by-omission: a
denied node is dropped, never rendered-disabled.
_Avoid_: NavPolicy / NavGuard (the package holds no policy — the host's stages do), middleware.

**NavContext**:
The minimal, host-vocabulary-free build context threaded through a build — the authenticated user, the
request, and an opaque `attributes` bag the host stashes its own context into (e.g. the current tenant).
Passed to gate stages; also bound into the container for the length of a `build()` so a host expander /
capability can resolve it (the `NavExpander` contract carries no context param).
_Avoid_: RequestContext (it is not the HTTP request; it wraps one), NavState.

**Gate-meta**:
An opaque, server-only `#[Hidden]` bag on `NavNode` (`withMeta([...])`) the host stashes its gating
tokens into for the `NavGate` stages to read. Build-time only — it never appears in `toArray()` /
`toJson()`, so no host gating vocabulary leaks to the client, and a rehydrated node arrives with empty
meta. A node with no meta always passes the gate. The package holds and hands these keys; it never
interprets them.
_Avoid_: attributes (that's `NavContext`'s host bag; gate-meta rides the *node*), props.

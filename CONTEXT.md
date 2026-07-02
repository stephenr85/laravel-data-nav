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

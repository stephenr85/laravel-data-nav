# laravel-data-nav — Context

A domain-agnostic navigation spine: a serializable nav tree with server-resolved active-state,
consumable by both Inertia/React (JSON) and Blade (array) hosts. It emits no HTML — rendering is
the host's job. Part of the `laravel-data-*` family (`laravel-data-schemas`,
`laravel-data-filters`).

## Language

**NavItem**:
One node of navigation — a `title`, an `href`, an optional `match` pattern, `children[]`, and the
resolved `active` / `activeTrail` flags. Framework-agnostic; serializes to JSON or array.
_Avoid_: MenuLink, NavLink, MenuItem.

**NavTree**:
An ordered collection of `NavItem`s forming one navigation region (a sidebar, a primary menu, a
Topics submenu). Hosts compose a full nav from several sources — chrome items plus contributed
subtrees.
_Avoid_: Menu, Navbar.

**NavResolver**:
The request-aware service that walks a `NavTree` once and **stamps** each item's `active` and
`activeTrail` server-side against the current route — the canonical active-state answer. Clients
prefer the stamped flags; client URL-matching is only a fallback.
_Avoid_: ActiveMatcher, current-menu.

**Active trail**:
The chain of ancestor `NavItem`s leading to the active leaf — what a parent section highlights on
even when the active item is a descendant.
_Avoid_: breadcrumb (that's a separate ordered path), open path.

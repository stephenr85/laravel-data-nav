# A standalone, framework-agnostic navigation package

> Refined by [ADR-0002](0002-polymorphic-nav-interface-and-popcorn-resolution.md): `NavItem`
> became an interface with `SchemaIdentity` DTO kinds (`NavLink`, `InvokableNavItem`), and the
> `NavResolver` service became the `data-nav/resolve` popcorn invocable. The framework-agnostic,
> serializable, no-HTML, own-leaf-package decision below still stands.

Every app in the family needs navigation, across render stacks (React/Inertia for numero and the
marketing site; Blade for thingsontv), and active-state handling was divergent and incomplete —
numero resolves it client-side (`useCurrentUrl` vs `usePage().url`), thingsontv has none. Nav is
entirely domain-agnostic: it knows nothing about Splicewire, tenancy, or compositions. Putting it
in the satellite package would violate that package's "runtime of *being a Splicewire tenant*"
boundary.

**Decision:** ship nav as its own leaf package, `rushing/laravel-data-nav`, in the same
domain-agnostic `laravel-data-*` family as `laravel-data-schemas` / `laravel-data-filters`.

- `NavItem` / `NavTree` are `spatie/laravel-data` structures (`title`, `href`, `match`,
  `children[]`, `active`, `activeTrail`) that serialize to JSON for Inertia *and* to an array
  for Blade — one contract, both stacks.
- A `NavResolver` walks the tree against the current request once and **stamps `active` +
  `activeTrail` server-side** (canonical). Clients prefer the stamped flags (no SSR first-paint
  flicker, parent-trail handled); client URL-matching is a fallback. The resolver is request-aware
  — runtime behavior alongside the Data classes, mirroring how `laravel-data-filters` pairs Data
  attributes with query-building.
- The package **emits no HTML**; rendering is each host's job.

**Rejected — `spatie/menu` / `spatie/laravel-menu`:** both are HTML-string renderers with no
first-class serializable tree; their only asset for us is `setActiveFromRequest()`. Not worth a
dependency that renders nothing in a React/Inertia-first family.

**Consequences:** the satellite package stays nav-free (boundary preserved). `laravel-data-nav`
is a new leaf in the git-resolved closure/doctor. `laravel-splicewire-satellite-publishing`
depends on it and contributes a `topicsTree()` provider (recursive `Category` tree → `NavTree`);
host apps depend on it directly for their site-chrome `NavItem`s. Publicly open-source-able.

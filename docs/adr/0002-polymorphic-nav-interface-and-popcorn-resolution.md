# A polymorphic NavItem interface + popcorn-resolved active-state

[ADR-0001](0001-framework-agnostic-nav-package.md) shipped nav as a concrete `NavItem` Data class
and a request-aware `NavResolver` service. Two things pulled it out of family shape: (1) its Data
classes skipped `laravel-data-schemas`, so they had no versioned `$id` / JSON Schema / TS parity
the rest of the family gets; and (2) `NavResolver` was a monolithic URL "highlighter", where the
family models behavior as interfaces + `laravel-popcorn` invocables. A concrete node class also
left no room for a menu that builds itself.

**Decision:** rework nav into a polymorphic node model resolved by an invocable.

- `NavItem` is an **interface** — the node contract. Node kinds are non-`final` `SchemaIdentity`
  Data classes over an abstract `NavNode` base (spatie `PropertyMorphableData`, a `kind`
  discriminator): `NavLink` (static, `nav/link`) and `InvokableNavItem` (`nav/invokable-item`).
  A `NavTree` (`nav/tree`) carries them as a discriminable union serializing to JSON and array.
- `InvokableNavItem` **knows what to invoke**: it holds a registered popcorn invocable name (+
  optional input) and declares a subtree it does not eagerly hold. This is how a self-building
  menu (e.g. Topics) is expressed — the item defers its subtree to a capability.
- Active-state resolution is the **`data-nav/resolve` popcorn invocable** (Local binding),
  `{ tree, path } → { tree }`: it expands each `InvokableNavItem` via the shared
  `InvocableRegistry` (recursively) then stamps `active` / `activeTrail` through a pluggable
  `NavMatcher` (default `PathNavMatcher`: `match` glob, else exact `href`). An unknown invocable
  name degrades to empty children, never an error. `Nav::resolve()` is the thin request adapter.
- New git-resolved deps: `rushing/laravel-data-schemas`, `rushing/laravel-popcorn`.

**Consequences:** nav joins the family Data → JSON Schema → TS pipeline and the popcorn capability
model. A host adds an `InvokableNavItem` pointing at a registered capability and the menu builds
itself on resolve — the publishing-side `topicsTree()` provider becomes such a capability (a
follow-up). Still framework/domain-agnostic and emits no HTML.

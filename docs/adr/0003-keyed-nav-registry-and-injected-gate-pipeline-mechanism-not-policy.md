# A keyed nav registry + an injected gate pipeline — mechanism, not policy

[ADR-0002](0002-polymorphic-nav-interface-and-popcorn-resolution.md) gave the package a polymorphic node
model with popcorn-resolved active-state — but it owned only the tree *vocabulary* and the *resolve* walk.
It had **no builder** (a host hand-assembled its `NavTree` and called `Nav::resolve()`) and **no gating**
(a host filtered nodes itself, before building). A consuming host (`splicewire-app`) therefore hand-rolled
its whole nav: two near-duplicate section arrays behind a realm branch, visibility/attachment/ordering
tangled in one pass, none of it reusable or unit-testable in isolation.

**Decision:** add the *generic* nav-building mechanism — a keyed registry and one injected gate seam —
while keeping the package free of any host vocabulary. Policy stays in the host.

- **`NavRegistry`** — keyed `register(key, factory)` / `build(key, NavContext)`. Realm/region collapses to
  a key. `build()` runs one pipeline reusing the existing walk and adding only an omit phase:
  **gate/omit → expand (`NavExpander`) → gate contributed children → stamp (`ResolveNav`)**. A gated-out
  node is dropped **before** its children are expanded (a hidden node never pays to build its subtree),
  and contributed children are gated the same way — gating is uniform at any depth. The stamp step reuses
  `ResolveNav` driven by a new **`StaticNavExpander`** so the already-expanded tree is stamped without
  being re-expanded (which would re-invoke capabilities and undo child-level gating).
- **`NavGate` + `Contracts/NavGateStage`** — the one net-new seam. `NavGate` holds ordered stages
  (`allows(node, context): bool`), ANDs them, short-circuits on the first deny, and is **bound empty by
  default** (no stages ⇒ everything visible). The package holds only the *mechanism*; a host registers
  ordered stages that read its own tokens off the node's opaque gate-meta and off the context.
- **`NavContext`** — a host-vocabulary-free build context (user + request + an opaque `attributes` bag),
  passed to stages and bound into the container for the length of a `build()` so a host expander/capability
  can resolve it (the `NavExpander` contract carries no context param).
- **Gate-meta on `NavNode`** — an opaque, server-only `#[Hidden] array $meta` (`withMeta()`). Build-time
  only: excluded from `toArray()` / `toJson()` and the TS transform, so no host gating vocabulary reaches
  the wire; a rehydrated node has empty meta. A node with no meta always passes the gate.
- The ServiceProvider binds `NavGate` and `NavRegistry` as singletons the host populates; the existing
  `NavMatcher` / `NavExpander` / `NavKindRegistry` bindings are untouched.

The model shift that keeps the net-new surface to a single seam: **there is no "section."** Gating and
contribution are properties of *nodes* applied at any depth, so contribution reuses the existing
`InvokableNavItem` / `NavExpander` seam rather than new machinery — only the omit phase is added.

**Consequences:** a host declares named navigations and plugs in its gating through contracts, reusing the
builder without inheriting the host's vocabulary. The mechanism (keyed registry, gate pipeline,
meta-stripping) is verified by the package's own tests with fake stages, fake nodes, and a spy expander.
Still framework/domain-agnostic and emits no HTML. The first consumer, `splicewire-app`, records the
host-side split (entitlement/permission stages + a Frame-resources contributor) and its rationale in its
own ADR-0120.

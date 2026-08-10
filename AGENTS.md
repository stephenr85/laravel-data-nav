> You are in **rushing/laravel-data-nav** — a domain-agnostic navigation spine for Laravel.

A polymorphic `NavItem` interface with SchemaIdentity DTO implementations (static `NavLink` + invocable-backed `InvokableNavItem`) and a `NavTree`, resolved server-side through a `laravel-popcorn` invocable. Consumable by both Inertia/React (JSON) and Blade (array) hosts. Emits no HTML. Part of the `laravel-data-*` family.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.

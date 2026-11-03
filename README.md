# rushing/laravel-data-nav

A domain-agnostic navigation spine for Laravel. You describe a navigation (a sidebar, a footer, a
settings menu) as a tree of `spatie/laravel-data` nodes, and the package builds it per request on
the server: it drops the nodes the current user may not see, fills dynamic sections, and marks the
active node and the trail to it. The result is plain data, so an Inertia/React page reads it as JSON
and a Blade view reads it as an array. The package emits no HTML.

## Installation

```bash
composer require rushing/laravel-data-nav
```

The service provider is auto-discovered.

## Example

Register a navigation from a service provider, then build it for the current request:

```php
use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavRegistry;

public function boot(): void
{
    $this->app->make(NavRegistry::class)->register('admin', fn (NavContext $context) => [
        NavLink::make('Dashboard', href: '/admin'),
        NavLink::make('Users', href: '/admin/users'),
    ]);
}
```

```php
$tree = app(NavRegistry::class)->build('admin', new NavContext(
    user: $request->user(),
    request: $request,
));

return inertia('Admin/Layout', ['nav' => $tree->toArray()]);
```

A node whose children are only known at request time is an `InvocableNavItem`, which names the
invocable that supplies them. Building a name nobody registered throws.

## Registries

This package declares three registries. The [`data-nav`](docs/registries/data-nav.mdx) page also
says which one you reach for.

- [`data-nav`](docs/registries/data-nav.mdx): the invocables that resolve a tree's active state and
  build the children of dynamic nodes. The package registers `data-nav.resolve`.
- [`data-nav.navigations`](docs/registries/data-nav.navigations.mdx): one factory per navigation
  region, built per request.
- [`data-nav.kinds`](docs/registries/data-nav.kinds.mdx): the map from a node's kind to its class,
  so custom nodes survive serialization. The package registers its own node kinds.

## Tests

```bash
composer install
vendor/bin/pest
```

## License

MIT

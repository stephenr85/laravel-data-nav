<?php

namespace Rushing\DataNav;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * A thin request adapter over the `data-nav.resolve` invocable — ergonomics so a
 * host resolves active-state against the current request without hand-building
 * the transport array. Hydrates the path, dispatches through data-nav's own
 * {@see NavInvocableRegistry}, and rehydrates a {@see NavTree}.
 */
class Nav
{
    public static function resolve(NavTree $tree, ?Request $request = null): NavTree
    {
        $container = Container::getInstance();

        $request ??= $container->make('request');

        /** @var NavInvocableRegistry $registry */
        $registry = $container->make(NavInvocableRegistry::class);

        $output = $registry->invoke('data-nav.resolve', [
            'tree' => $tree->toArray(),
            'path' => $request->path(),
        ]);

        return NavTree::from($output['tree']);
    }
}

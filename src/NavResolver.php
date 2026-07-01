<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * The request-aware service that walks a NavTree once and STAMPS each item's
 * `active` and `activeTrail` server-side against the current request — the
 * canonical active-state answer. Clients prefer the stamped flags; client-side
 * URL-matching is only a fallback.
 *
 * - `active` = this item's `match` pattern (Laravel `Request::is()` glob
 *   semantics, e.g. `blog/*`) matches the current path; or, with no `match`,
 *   the item's `href` exactly matches the current path (slash-normalized).
 * - `activeTrail` = true for any ancestor on the chain leading to an active
 *   descendant. The active leaf carries `active=true`; its ancestors carry
 *   `activeTrail=true`.
 */
final class NavResolver
{
    /**
     * Walk the tree and return a fresh tree with active-state stamped.
     */
    public function resolve(NavTree $tree, ?Request $request = null): NavTree
    {
        $request ??= $this->currentRequest();
        $path = $this->normalizePath($request->path());

        $items = array_map(
            fn (NavItem $item): NavItem => $this->stamp($item, $request, $path),
            $tree->items,
        );

        return new NavTree(items: $items);
    }

    /**
     * Recursively stamp one item and its subtree. An item is on the active
     * trail when any descendant resolves active; the item itself is active
     * when its own pattern/href matches the current path.
     */
    private function stamp(NavItem $item, Request $request, string $path): NavItem
    {
        $children = array_map(
            fn (NavItem $child): NavItem => $this->stamp($child, $request, $path),
            $item->children,
        );

        $active = $this->matches($item, $request, $path);

        $activeTrail = false;
        foreach ($children as $child) {
            if ($child->active || $child->activeTrail) {
                $activeTrail = true;
                break;
            }
        }

        return $item->withState(
            active: $active,
            activeTrail: $activeTrail,
            children: $children,
        );
    }

    /**
     * Does this item match the current request path? A `match` pattern uses
     * Laravel's glob semantics; otherwise the `href` must match the path
     * exactly (both slash-normalized).
     */
    private function matches(NavItem $item, Request $request, string $path): bool
    {
        if ($item->match !== null && $item->match !== '') {
            return $request->is($this->normalizePattern($item->match));
        }

        return $this->normalizePath($item->href) === $path;
    }

    private function normalizePath(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('#^https?://[^/]+#i', '', $value) ?? $value;
        $value = strtok($value, '?#');
        $value = trim((string) $value, '/');

        return $value === '' ? '/' : $value;
    }

    private function normalizePattern(string $pattern): string
    {
        $pattern = trim($pattern, '/');

        return $pattern === '' ? '/' : $pattern;
    }

    private function currentRequest(): Request
    {
        return Container::getInstance()->make('request');
    }
}

<?php

declare(strict_types=1);

namespace Rushing\DataNav;

use Illuminate\Support\Str;
use Rushing\DataNav\Contracts\NavItem;
use Rushing\DataNav\Contracts\NavMatcher;

/**
 * The default {@see NavMatcher}: a node is active when its `match` glob (Laravel
 * `Str::is` semantics, e.g. `blog/*`) matches the path; with no `match`, its
 * `href` must equal the path exactly. Paths and patterns are slash-normalized so
 * `/blog`, `blog`, and `blog/` compare equal.
 *
 * Infrastructure, so `final` — the interface, not this class, is the seam.
 */
final class PathNavMatcher implements NavMatcher
{
    public function matches(NavItem $item, string $path): bool
    {
        $path = $this->normalizePath($path);

        $pattern = $item->matchPattern();
        if ($pattern !== null && $pattern !== '') {
            return Str::is($this->normalizePattern($pattern), $path);
        }

        $href = $item->href();
        if ($href === null) {
            return false;
        }

        return $this->normalizePath($href) === $path;
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
}

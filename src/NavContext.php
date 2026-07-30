<?php

namespace Rushing\DataNav;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * The minimal, host-vocabulary-free build context threaded through a nav build —
 * everything a gate stage (or, later, an expander) needs to decide a node
 * without the package naming any host concept. It carries the authenticated
 * user, the request, and an opaque `attributes` bag the host stashes its own
 * context into (e.g. the current tenant under a host-chosen key).
 *
 * Deliberately references no entitlement / permission / Frame vocabulary: a
 * stage reads whatever it needs off the node's opaque {@see NavNode::$meta} and
 * off this context's typed user/request plus the untyped `attributes` bag. This
 * is the seam that keeps the mechanism (package) apart from the policy (host).
 */
class NavContext
{
    /**
     * @param  array<string, mixed>  $attributes  an opaque host-context bag (e.g. `['tenant' => $tenant]`)
     */
    public function __construct(
        public readonly ?Authenticatable $user = null,
        public readonly ?Request $request = null,
        public readonly array $attributes = [],
    ) {}

    /**
     * Read one host-context attribute, or a default when absent — sugar over the
     * `attributes` bag so a stage reads `$context->attribute('tenant')` instead
     * of poking the array.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}

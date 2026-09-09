<?php

use Illuminate\Container\Container;
use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavRegistry;

/**
 * `NavContext` must be bound for the length of one build and no longer.
 *
 * ## The defect this pins
 *
 * `build()` threads the context to expansion by binding it into the container, because the
 * `NavExpander` contract carries no context. It used to do that with a bare `instance()` and a comment
 * claiming the binding lasted "for the length of the build" — which `instance()` does not do. It binds
 * for the container's lifetime, and nothing unbound it.
 *
 * `NavContext` carries `?Authenticatable $user` and `?Request $request`. So after any build, a stale
 * context stayed bound, and it is **reachable**: `FrameResourcesInvocable` in
 * `splicewire/laravel-beam-ux` and its host counterpart both do
 * `$container->bound(NavContext::class) ? $container->make(...) : …`, and `bound()` stays true forever
 * after the first build.
 *
 * Under request-per-process PHP the stale context is at worst the same request's own. Under a
 * persistent worker — Octane/FrankenPHP, or the queue worker, which is one already — it is the
 * **previous request's user**, feeding a navigation/resource-gating path. That is a per-actor leak on
 * an auth-adjacent object, and it is silent: a plausible tree comes back either way.
 */
it('leaves nothing bound when there was no context before the build', function () {
    $container = Container::getInstance();
    $container->forgetInstance(NavContext::class);

    expect($container->bound(NavContext::class))->toBeFalse();

    $registry = app(NavRegistry::class);
    $registry->register('lifetime-test', fn (NavContext $c): array => []);
    $registry->build('lifetime-test', new NavContext);

    // The assertion the bare `instance()` failed. `bound()` must read false again, or every later
    // `bound() ? make() : …` in the estate silently picks up this build's actor.
    expect($container->bound(NavContext::class))->toBeFalse();
});

it('hands an outer build its own context back after a nested one', function () {
    $container = Container::getInstance();
    $container->forgetInstance(NavContext::class);

    $outer = new NavContext(attributes: ['which' => 'outer']);
    $inner = new NavContext(attributes: ['which' => 'inner']);

    $seenByOuterAfterNested = null;

    $registry = app(NavRegistry::class);
    $registry->register('nested-inner', fn (NavContext $c): array => []);
    $registry->register('nested-outer', function (NavContext $c) use ($registry, $inner, &$seenByOuterAfterNested): array {
        // A capability triggering a nested build is the case a plain forget-after would break: it would
        // leave the outer build running with nothing bound.
        $registry->build('nested-inner', $inner);
        $seenByOuterAfterNested = Container::getInstance()->make(NavContext::class);

        return [];
    });

    $registry->build('nested-outer', $outer);

    expect($seenByOuterAfterNested)->toBe($outer)
        ->and($container->bound(NavContext::class))->toBeFalse();
});

it('restores a pre-existing binding rather than dropping it', function () {
    $container = Container::getInstance();
    $preexisting = new NavContext(attributes: ['which' => 'preexisting']);
    $container->instance(NavContext::class, $preexisting);

    $registry = app(NavRegistry::class);
    $registry->register('restore-test', fn (NavContext $c): array => []);
    $registry->build('restore-test', new NavContext(attributes: ['which' => 'build']));

    // Restore, not forget — a host that deliberately bound a context before calling build() must still
    // have it afterwards.
    expect($container->make(NavContext::class))->toBe($preexisting);

    $container->forgetInstance(NavContext::class);
});

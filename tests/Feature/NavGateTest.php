<?php

use Rushing\DataNav\Contracts\NavGateStage;
use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavNode;

// Ticket 02: the one new gate seam — NavContext (host-free build context),
// the NavGateStage contract, and the NavGate pipeline runner (ordered stages,
// short-circuit on first deny, empty = allow all).

/** A fake stage that allows/denies by a fixed verdict, recording every call. */
function fakeStage(bool $verdict, ?array &$calls = null): NavGateStage
{
    return new class($verdict, $calls) implements NavGateStage
    {
        public function __construct(private bool $verdict, private ?array &$calls) {}

        public function allows(NavNode $node, NavContext $context): bool
        {
            if ($this->calls !== null) {
                $this->calls[] = $node->title();
            }

            return $this->verdict;
        }
    };
}

it('holds user, request, and an opaque attributes bag', function () {
    $context = new NavContext(attributes: ['tenant' => 'acme']);

    expect($context->user)->toBeNull()
        ->and($context->request)->toBeNull()
        ->and($context->attribute('tenant'))->toBe('acme')
        ->and($context->attribute('missing', 'fallback'))->toBe('fallback');
});

it('allows everything when the pipeline is empty', function () {
    $gate = new NavGate;
    $node = NavLink::make(title: 'Studio', href: '/studio');

    expect($gate->allows($node, new NavContext))->toBeTrue();
});

it('denies when any single stage denies', function () {
    $gate = (new NavGate)->through(fakeStage(false));
    $node = NavLink::make(title: 'Studio', href: '/studio');

    expect($gate->allows($node, new NavContext))->toBeFalse();
});

it('allows only when every stage allows', function () {
    $gate = (new NavGate)->through(fakeStage(true))->through(fakeStage(true));
    $node = NavLink::make(title: 'Studio', href: '/studio');

    expect($gate->allows($node, new NavContext))->toBeTrue();
});

it('short-circuits: a later stage is not consulted once an earlier one denies', function () {
    $calls = [];
    $gate = (new NavGate)
        ->through(fakeStage(false, $calls))  // denies first
        ->through(fakeStage(true, $calls));  // must never be called

    $gate->allows(NavLink::make(title: 'Studio', href: '/studio'), new NavContext);

    // Only the first (denying) stage recorded a call.
    expect($calls)->toBe(['Studio']);
});

it('lets a stage read the node gate-meta: denies the matching node, allows a meta-less one', function () {
    // A stage that denies a node declaring a `permission` the context user lacks.
    $stage = new class implements NavGateStage
    {
        public function allows(NavNode $node, NavContext $context): bool
        {
            $required = $node->meta['permission'] ?? null;
            if ($required === null) {
                return true; // ungated node passes
            }

            $granted = $context->attribute('grants', []);

            return in_array($required, $granted, true);
        }
    };

    $gate = (new NavGate)->through($stage);
    $context = new NavContext(attributes: ['grants' => ['studio.view']]);

    $gated = NavLink::make(title: 'Platform', href: '/platform')->withMeta(['permission' => 'platform.view']);
    $ungated = NavLink::make(title: 'Knowledge', href: '/knowledge');
    $granted = NavLink::make(title: 'Studio', href: '/studio')->withMeta(['permission' => 'studio.view']);

    expect($gate->allows($gated, $context))->toBeFalse()
        ->and($gate->allows($ungated, $context))->toBeTrue()
        ->and($gate->allows($granted, $context))->toBeTrue();
});

it('binds NavGate empty by default and lets a host register ordered stages onto it', function () {
    $gate = app(NavGate::class);

    expect($gate)->toBeInstanceOf(NavGate::class)
        ->and($gate->stages())->toBe([]);

    // Same singleton instance is mutated by host registration.
    $gate->through(fakeStage(false));
    expect(app(NavGate::class)->stages())->toHaveCount(1);
});

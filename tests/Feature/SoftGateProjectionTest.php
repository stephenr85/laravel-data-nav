<?php

use Rushing\DataNav\Contracts\NavGateStage;
use Rushing\DataNav\Contracts\NavVerdictStage;
use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavGateVerdict;
use Rushing\DataNav\NavLink;
use Rushing\DataNav\NavLocked;
use Rushing\DataNav\NavNode;
use Rushing\DataNav\NavTree;

// Ticket 11 (Frame OS ADR-0014 §A4): the soft-gate — a node survives into the emitted manifest
// carrying a wire-visible `locked{reason, upsell}` projection (present-but-locked / monetization),
// while a hard-gated node stays omitted (protection). The field is POPULATED by beam's projection
// (ticket 08); this ticket makes it expressible + serialized and extends NavGate's binary keep/drop.

/** A fake three-way stage returning a fixed verdict. */
function verdictStage(NavGateVerdict $verdict): NavVerdictStage
{
    return new class($verdict) implements NavVerdictStage
    {
        public function __construct(private NavGateVerdict $verdict) {}

        public function decide(NavNode $node, NavContext $context): NavGateVerdict
        {
            return $this->verdict;
        }
    };
}

/** A fake binary stage (the existing keep/drop shape). */
function binaryStage(bool $verdict): NavGateStage
{
    return new class($verdict) implements NavGateStage
    {
        public function __construct(private bool $verdict) {}

        public function allows(NavNode $node, NavContext $context): bool
        {
            return $this->verdict;
        }
    };
}

it('makes a soft-locked node carry locked{reason,upsell} in both toArray and toJson', function () {
    $node = NavLink::make(title: 'Studio', href: '/studio')->locked('Available on the Songwriter plan', 'go-songwriter');

    $array = $node->toArray();
    expect($array)->toHaveKey('locked')
        ->and($array['locked']['reason'])->toBe('Available on the Songwriter plan')
        ->and($array['locked']['upsell'])->toBe('go-songwriter')
        ->and($node->toJson())->toContain('Available on the Songwriter plan')
        ->and($node->toJson())->toContain('go-songwriter');
});

it('leaves an ungated node with a null locked (not locked, existing nodes unaffected)', function () {
    $node = NavLink::make(title: 'Home', href: '/');

    expect($node->locked)->toBeNull()
        ->and($node->toArray()['locked'])->toBeNull();
});

it('supports a lock with no upsell (reason only)', function () {
    $node = NavLink::make(title: 'X', href: '/x')->locked('Coming soon');

    expect($node->locked)->toBeInstanceOf(NavLocked::class)
        ->and($node->locked->reason)->toBe('Coming soon')
        ->and($node->locked->upsell)->toBeNull();
});

it('apply(): a hard-denied node is omitted entirely (protection, unchanged binary behavior)', function () {
    $node = NavLink::make(title: 'Operator', href: '/operator');

    expect((new NavGate)->through(binaryStage(false))->apply($node, new NavContext))->toBeNull()
        ->and((new NavGate)->through(verdictStage(NavGateVerdict::deny()))->apply($node, new NavContext))->toBeNull();
});

it('apply(): a soft-locked node is KEPT with its locked projection stamped (monetization)', function () {
    $node = NavLink::make(title: 'Studio', href: '/studio');

    $out = (new NavGate)->through(verdictStage(NavGateVerdict::lock('Upgrade to unlock', 'go-songwriter')))
        ->apply($node, new NavContext);

    expect($out)->not->toBeNull()
        ->and($out->locked)->toBeInstanceOf(NavLocked::class)
        ->and($out->locked->reason)->toBe('Upgrade to unlock')
        ->and($out->locked->upsell)->toBe('go-songwriter');
});

it('apply(): an allowed node passes through unchanged (not locked)', function () {
    $node = NavLink::make(title: 'Home', href: '/');

    $out = (new NavGate)->through(verdictStage(NavGateVerdict::allow()))->apply($node, new NavContext);

    expect($out)->not->toBeNull()
        ->and($out->locked)->toBeNull();
});

it('apply(): a hard deny overrides a soft lock (hard wins, node omitted)', function () {
    $node = NavLink::make(title: 'Studio', href: '/studio');

    $out = (new NavGate)
        ->through(verdictStage(NavGateVerdict::lock('locked', 'plan')))
        ->through(verdictStage(NavGateVerdict::deny()))
        ->apply($node, new NavContext);

    expect($out)->toBeNull();
});

it('preserves the binary allows() path: a lock counts as allowed, a deny denies', function () {
    $node = NavLink::make(title: 'Studio', href: '/studio');

    expect((new NavGate)->allows($node, new NavContext))->toBeTrue() // empty = allow all
        ->and((new NavGate)->through(verdictStage(NavGateVerdict::lock('r')))->allows($node, new NavContext))->toBeTrue()
        ->and((new NavGate)->through(verdictStage(NavGateVerdict::deny()))->allows($node, new NavContext))->toBeFalse()
        ->and((new NavGate)->through(binaryStage(false))->allows($node, new NavContext))->toBeFalse();
});

it('serializes the locked projection across the morph round-trip (wire-visible, unlike meta)', function () {
    $tree = NavTree::make([
        NavLink::make(title: 'Studio', href: '/studio')->locked('Available on the Songwriter plan', 'go-songwriter'),
    ]);

    // The wire DOES carry locked (unlike the #[Hidden] meta bag).
    expect($tree->toJson())->toContain('Available on the Songwriter plan');

    $rehydrated = NavTree::from(json_decode($tree->toJson(), true));

    expect($rehydrated->items[0])->toBeInstanceOf(NavLink::class)
        ->and($rehydrated->items[0]->locked)->toBeInstanceOf(NavLocked::class)
        ->and($rehydrated->items[0]->locked->reason)->toBe('Available on the Songwriter plan')
        ->and($rehydrated->items[0]->locked->upsell)->toBe('go-songwriter');
});

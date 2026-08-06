<?php

namespace Rushing\DataNav\Contracts;

use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavGateVerdict;
use Rushing\DataNav\NavNode;

/**
 * The richer gate stage (Frame OS ticket 11) — returns a three-way {@see NavGateVerdict}
 * (allow / deny / lock) instead of the binary {@see NavGateStage}'s `allows(): bool`. This is what
 * lets a stage express *present-but-locked* (soft-gate / monetization) as distinct from *omitted*
 * (hard-gate / protection).
 *
 * Same discipline as {@see NavGateStage}: the *mechanism* (folding stages, honoring deny vs lock)
 * lives in {@see \Rushing\DataNav\NavGate}; the *policy* — when to allow, deny, or lock, and with
 * what reason/upsell — is the host's, read off the node's opaque {@see NavNode::$meta} and the
 * {@see NavContext}. A stage that has no opinion returns `NavGateVerdict::allow()`.
 */
interface NavVerdictStage
{
    public function decide(NavNode $node, NavContext $context): NavGateVerdict;
}

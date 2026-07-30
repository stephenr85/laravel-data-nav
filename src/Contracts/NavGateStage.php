<?php

namespace Rushing\DataNav\Contracts;

use Rushing\DataNav\NavContext;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavNode;

/**
 * The injected gating seam — the one new package contract behind the nav
 * builder. Given a node and the build context, a stage decides whether that
 * node is allowed to appear (secure-by-omission: a denied node is dropped, never
 * rendered-disabled).
 *
 * The *mechanism* — running an ordered set of stages and short-circuiting on the
 * first deny — lives in the package ({@see NavGate}). The
 * *policy* — what "allowed" means — is entirely the host's: a stage reads the
 * host's own tokens off the node's opaque {@see NavNode::$meta} and off
 * {@see NavContext}, so the package never interprets any host vocabulary. A node
 * with no relevant meta should pass (a stage returns true), keeping ungated
 * nodes visible by default.
 */
interface NavGateStage
{
    public function allows(NavNode $node, NavContext $context): bool;
}

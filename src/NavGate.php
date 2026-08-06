<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavGateStage;
use Rushing\DataNav\Contracts\NavVerdictStage;

/**
 * The gate pipeline runner — the *mechanism* half of the one new gate seam. It
 * holds an ordered list of host-registered {@see NavGateStage}s and, for a given
 * node + {@see NavContext}, allows the node only if **every** stage allows it,
 * **short-circuiting on the first deny** (later stages are not consulted once a
 * node is denied).
 *
 * Bound **empty by default** (see the package ServiceProvider): with no stages,
 * every node passes, so a host that registers nothing gets today's
 * everything-visible behavior. The host pushes stages in the order it wants them
 * evaluated (e.g. entitlement, then permission).
 *
 * The runner names no host vocabulary — it only orchestrates the injected
 * stages, each of which reads whatever it needs off the node's opaque
 * {@see NavNode::$meta} and the context.
 */
class NavGate
{
    /**
     * @param  array<int, NavGateStage|NavVerdictStage>  $stages
     */
    public function __construct(
        private array $stages = [],
    ) {}

    /**
     * Append a stage to the pipeline (evaluated after those already registered). Accepts a binary
     * {@see NavGateStage} (allow/deny) or a three-way {@see NavVerdictStage} (allow/deny/lock).
     */
    public function through(NavGateStage|NavVerdictStage $stage): static
    {
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * @return array<int, NavGateStage|NavVerdictStage>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * Whether the node clears the whole pipeline — true when no stage denies it. Short-circuits on
     * the first deny. A {@see NavVerdictStage} that returns a *lock* counts as allowed here (the node
     * survives, just locked); use {@see apply()} to obtain the locked node. An empty pipeline allows
     * everything. Preserved binary behavior for hosts that only ever keep/drop.
     */
    public function allows(NavNode $node, NavContext $context): bool
    {
        foreach ($this->stages as $stage) {
            if ($stage instanceof NavVerdictStage) {
                if ($stage->decide($node, $context)->isDenied()) {
                    return false;
                }

                continue;
            }

            if (! $stage->allows($node, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fold a node through the pipeline into a keep/drop/lock outcome — the three-way extension of the
     * binary keep/drop (Frame OS ticket 11):
     *
     *  - any stage DENIES (a binary stage returns false, or a verdict stage denies) → the node is
     *    OMITTED: returns `null` (protection by construction, unchanged from the binary path).
     *  - no deny, but a {@see NavVerdictStage} LOCKS → the node is KEPT, stamped with the first lock's
     *    {@see NavLocked} projection ({@see NavNode::locked()}): present-but-locked (monetization).
     *  - otherwise → the node is kept unchanged.
     *
     * Deny short-circuits (a hard-gated node is never also locked). The first lock wins its
     * reason/upsell; later stages are still consulted for a deny, which overrides the lock.
     */
    public function apply(NavNode $node, NavContext $context): ?NavNode
    {
        $lock = null;

        foreach ($this->stages as $stage) {
            if ($stage instanceof NavVerdictStage) {
                $verdict = $stage->decide($node, $context);
                if ($verdict->isDenied()) {
                    return null;
                }
                if ($verdict->isLocked() && $lock === null) {
                    $lock = $verdict;
                }

                continue;
            }

            if (! $stage->allows($node, $context)) {
                return null;
            }
        }

        return $lock === null ? $node : $node->locked($lock->reason, $lock->upsell);
    }
}

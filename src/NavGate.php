<?php

namespace Rushing\DataNav;

use Rushing\DataNav\Contracts\NavGateStage;

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
     * @param  array<int, NavGateStage>  $stages
     */
    public function __construct(
        private array $stages = [],
    ) {}

    /**
     * Append a stage to the pipeline (evaluated after those already registered).
     */
    public function through(NavGateStage $stage): static
    {
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * @return array<int, NavGateStage>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * Whether the node clears the whole pipeline — true when every stage allows
     * it. Denies (returns false) on the first stage that rejects, without
     * consulting the rest. An empty pipeline allows everything.
     */
    public function allows(NavNode $node, NavContext $context): bool
    {
        foreach ($this->stages as $stage) {
            if (! $stage->allows($node, $context)) {
                return false;
            }
        }

        return true;
    }
}

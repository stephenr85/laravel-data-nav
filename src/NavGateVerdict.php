<?php

namespace Rushing\DataNav;

/**
 * A three-way gate verdict (Frame OS ticket 11) — the extension of the binary keep/drop that lets a
 * stage express *present-but-locked* alongside *allow* and *hard-deny*:
 *
 *  - **allow**  → the node is kept, unchanged.
 *  - **deny**   → the node is OMITTED (protection by construction — existing binary behavior).
 *  - **lock**   → the node is KEPT but stamped with a wire-visible {@see NavLocked} (monetization).
 *
 * A plain {@see Contracts\NavGateStage} (`allows(): bool`) still expresses only allow/deny; a
 * {@see Contracts\NavVerdictStage} can additionally return a lock. {@see NavGate::apply()} folds a
 * node through both kinds.
 */
final class NavGateVerdict
{
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reason = null,
        public readonly ?string $upsell = null,
    ) {}

    public static function allow(): self
    {
        return new self('allow');
    }

    public static function deny(): self
    {
        return new self('deny');
    }

    public static function lock(string $reason, ?string $upsell = null): self
    {
        return new self('lock', $reason, $upsell);
    }

    public function isAllowed(): bool
    {
        return $this->outcome === 'allow';
    }

    public function isDenied(): bool
    {
        return $this->outcome === 'deny';
    }

    public function isLocked(): bool
    {
        return $this->outcome === 'lock';
    }
}

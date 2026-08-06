<?php

namespace Rushing\DataNav;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The wire-visible soft-lock projection on a nav node (Frame OS ADR-0014 §A4, ticket 11).
 *
 * Unlike the server-only {@see NavNode::$meta} (`#[Hidden]`, gate tokens that never reach the
 * client), a `NavLocked` is a **serialized** field: it lets a node survive into the emitted manifest
 * as *present-but-locked* — the "monetized-but-unreached" state a hard-gated (omitted) node cannot
 * express. It carries the human-facing `reason` and an opaque `upsell` token the host renderer turns
 * into an upgrade CTA (a plan key, an upgrade href — the package never interprets it).
 *
 * The field is POPULATED by beam's manifest projection (ticket 08), which decides hard→absence vs
 * soft→locked; this DTO + {@see NavNode::locked()} only make that state expressible and serialized.
 */
#[TypeScript]
class NavLocked extends Data
{
    public function __construct(
        /** Why the node is locked, shown to the user (e.g. "Available on the Songwriter plan"). */
        public string $reason,
        /**
         * An opaque host token the renderer maps to an upgrade action — a plan key, an upgrade
         * route/href, or a feature id. Nullable: a lock may state a reason with no actionable upsell.
         */
        public ?string $upsell = null,
    ) {}
}

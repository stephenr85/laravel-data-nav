<?php

namespace Rushing\DataNav;

use Rushing\Popcorn\Contracts\Invocable;
use Rushing\Popcorn\InvocableRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\RegistryArity;

/**
 * data-nav's own branch of the keyspace — the capabilities this package owns, and nobody else's.
 *
 * ## Why a subclass rather than a second instance
 *
 * {@see IsRegistry::of()} reflects the attribute off the RUNTIME class and PHP does not inherit class
 * attributes, so `BasicRegistry::for($this)` in {@see InvocableRegistry}'s constructor reads whatever
 * the concrete class declares — and throws outright if that is nothing. Declaring the root here is
 * therefore the whole subclass: no method is overridden, and none needs to be.
 *
 * It is also what keeps this registry VISIBLE. Registry-kernel ticket 35's gating audit scopes to
 * classes carrying `#[IsRegistry]`, and ticket 21 settled that a class not declaring it is not a
 * registry. An owner that took its own root by handing a declaration to a shared instance would own a
 * branch of the keyspace that no static check could see.
 *
 * ## `data-nav.resolve` does not move
 *
 * The one capability here is already spelled under this root, and {@see \Rushing\Popcorn\Registries\
 * BasicRegistry::door()} stamps only keys NOT already beneath it. So the name every call site uses is
 * unchanged — it was absolute all along; what changes is that it is now owned rather than pooled.
 */
#[IsRegistry(
    root: 'data-nav',
    of: 'navigation capabilities — active-state resolution and the invocable-backed node expanders',
    arity: RegistryArity::PickOne,
    entryType: Invocable::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'A host overriding `data-nav.resolve` with its own matcher/expander composition is the swap '
        .'seam this inherits from InvocableRegistry, not an accident.',
)]
class NavInvocableRegistry extends InvocableRegistry {}

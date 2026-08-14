<?php

declare(strict_types=1);

namespace Rolland\Tree\Exceptions;

use DomainException;

/**
 * The caller named a neighbour it could not have aimed at.
 *
 * ⚠️ Three distinct causes share this ONE type on purpose:
 *
 *   1. the reference is not in the complete destination group;
 *   2. the reference is in the group but was not among the rendered siblings;
 *   3. the reference is the node being moved.
 *
 * They are one fact from the caller's perspective — *you named something you could
 * not have aimed at* — and separating them would let a caller distinguish "this
 * node exists but you cannot see it" from "this node does not exist", which is
 * precisely the disclosure FR-037 prevents in the ARIA counts. Principle IV's
 * reasoning constrains the core, not only the bridge.
 *
 * Do not add subclasses. Do not add a `reason` property.
 */
final class UnreachableReferenceException extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('tree::tree.refused.unreachable_reference'));
    }
}

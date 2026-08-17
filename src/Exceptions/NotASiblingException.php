<?php

declare(strict_types=1);

namespace Rolland\Tree\Exceptions;

use DomainException;

/**
 * `ReorderSiblings` was given a key that is not a member of the group it named.
 *
 * ⚠️ **Added because silently skipping it was worse than refusing** (finding F23).
 * The action looked each key up scoped to the group and did `continue` on a miss.
 * But the write loop assigns each member the index it held in the GIVEN list, so a
 * skipped key leaves that index unused and the group ends up **non-contiguous** —
 * breaking `AGENTS.md` R-009 from a call the caller was told nothing about.
 *
 * ⚠️ A SEPARATE type from `UnreachableReferenceException`, not a reuse of it. That
 * exception is deliberately vague across three causes because distinguishing them
 * would disclose whether a hidden node exists (FR-037). No such disclosure arises
 * here: a reorder's keys are the caller's own claim about one group it already
 * named, so telling it plainly that a key does not belong reveals nothing it did
 * not supply. Conflating the two would have made the vague type vaguer.
 *
 * Found by the first consumer, whose own action raised this refusal and whose
 * frozen test asserts it.
 */
final class NotASiblingException extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('tree::tree.refused.not_a_sibling'));
    }
}

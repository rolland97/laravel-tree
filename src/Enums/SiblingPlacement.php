<?php

declare(strict_types=1);

namespace Rolland\Tree\Enums;

/**
 * How a node is placed relative to a named neighbour.
 *
 * ⚠️ There is no `AtIndex` case and there never will be one. Constitution
 * Principle II: a move names a neighbour, never an index.
 */
enum SiblingPlacement
{
    /** Immediately before the reference, among the reference's siblings. */
    case Before;

    /** Immediately after it. */
    case After;

    /** Last among the destination parent's children. */
    case LastChild;

    /**
     * Does this placement require a reference sibling?
     *
     * `LastChild` names no neighbour, which is why it was already correct in the
     * source application before the 071 defect — there was no index to get wrong.
     */
    public function needsReference(): bool
    {
        return $this !== self::LastChild;
    }
}

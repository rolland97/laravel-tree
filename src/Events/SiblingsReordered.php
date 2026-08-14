<?php

declare(strict_types=1);

namespace Rolland\Tree\Events;

/**
 * One sibling group's order was rewritten.
 *
 * `$orderedKeys` is the COMPLETE group as written, in order — not the subset the
 * actor could see. A listener recording an audit entry needs the whole truth.
 */
final class SiblingsReordered
{
    /**
     * @param  list<int|string>  $orderedKeys
     */
    public function __construct(
        public readonly int|string|null $parentId,
        public readonly array $orderedKeys,
    ) {}
}

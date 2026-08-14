<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests;

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Page;

/**
 * ⚠️ Every reader here goes to the STORED ROWS.
 *
 * Constitution Principle I / AGENTS.md R-024: a test comparing a page against the
 * same accessor the page rendered from cannot see a defect inside that accessor.
 * Nothing in this file may call the package's own read path.
 */
final class Stored
{
    /**
     * Category ids in the group under `$parentId`, ordered by the STORED position
     * then by name — the read order, expressed in raw SQL rather than by asking
     * the package what it thinks the order is.
     *
     * @return list<int>
     */
    public static function order(?int $parentId): array
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }

    /**
     * The raw position values of that group, in the same order.
     *
     * ⚠️ Asserted in its OWN test case, never appended to an order assertion with
     * `->and()`. A chain stops at the first failure, so a contiguity assertion
     * hung off an ordering one could never be watched failing on its own
     * (quickstart.md § SC-002/SC-003).
     *
     * @return list<int>
     */
    public static function positions(?int $parentId): array
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->orderBy('name')
            ->pluck('position')
            ->map(intval(...))
            ->all();
    }

    /**
     * The same two readers for the Page fixture, whose columns are all different.
     *
     * @return list<int>
     */
    public static function pageOrder(?int $sectionId): array
    {
        return Page::query()
            ->where('section_id', $sectionId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function pagePositions(?int $sectionId): array
    {
        return Page::query()
            ->where('section_id', $sectionId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->pluck('sort_order')
            ->map(intval(...))
            ->all();
    }
}

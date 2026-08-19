<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

/**
 * A host whose page has no concept of sibling ORDER at all (PA-18).
 *
 * ⚠️ It EXTENDS `OrderedTwinTreePage` and overrides `treeReorderEnabled()` — plus the
 * slug and title it needs to be routable. Nothing else. That is what makes the C5–C8
 * "unchanged" guards mean anything: the two pages are the same page apart from the
 * one answer under test, by construction rather than by inspection.
 *
 * ⚠️ `canMoveNode()` and `authorizeTreeMove()` are left at their defaults — both
 * `true` — and that is the discrimination the contract asks for. If a row still
 * refuses a pick-up, or the live region still says "you cannot move this", then the
 * announcement came from the PERMISSION slot and PA-18 is not doing the work
 * (contract PA-18, C4).
 *
 * ⚠️ Two different questions, and the reason PA-18 exists rather than a page simply
 * answering `canMoveNode()` false everywhere. `canMoveNode()` answers "may this ACTOR
 * move this NODE": a false answer sets `data-ltree-immovable` while the drag handle
 * still renders and the keyboard still answers Space with a permission refusal. A page
 * that has retired ordering would then show a handle on every row and tell a
 * screen-reader user they lack permission for a concept the page does not have — a lie
 * in the only channel that user has (the consumer's research R1).
 */
final class UnorderedTreePage extends OrderedTwinTreePage
{
    protected static ?string $slug = 'unordered-tree';

    protected static ?string $title = 'Unordered tree';

    public function treeReorderEnabled(): bool
    {
        return false;
    }
}

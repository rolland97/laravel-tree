<?php

declare(strict_types=1);

namespace Rolland\Tree\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Support\SiblingGroup;
use Rolland\Tree\Support\TreeColumns;

final class MoveNode
{
    /**
     * Re-parent and/or re-position. The subtree follows. Atomic. Fires NodeMoved once.
     *
     * ⚠️ `$position` is an index THIS PACKAGE produced, via
     * `ResolveSiblingPlacement`. It is **not** a client-supplied index, and this is
     * the one place that distinction is easy to lose: a caller that computes this
     * integer any other way has reintroduced the defect the package exists to
     * prevent (constitution Principle II). Callers outside the package should call
     * `PlaceNode`, which names a neighbour and computes this index itself.
     *
     * The distinction cannot be expressed in a type, so it is stated here and
     * guarded by `tests/Core/PublicSurfaceTest.php`, which asserts this is the only
     * integer position in the entire public surface and reads this docblock.
     *
     * @throws CycleException|InvalidTargetException
     */
    public function handle(Model&TreeNode $node, ?TreeNode $newParent, int $position): void
    {
        $this->guardAgainstCycle($node, $newParent);

        // No node to ask when moving to the root. Demanding one would make it
        // impossible to move anything back out to the top level.
        if ($newParent !== null && ! $newParent->isValidTreeTarget()) {
            throw InvalidTargetException::make();
        }

        $previousParentId = $node->treeParentId();
        $newParentId = $newParent?->getKey();

        $originGroupChanged = SiblingGroup::key($previousParentId ?? '')
            !== SiblingGroup::key($newParentId ?? '');

        DB::transaction(function () use ($node, $newParent, $newParentId, $position, $originGroupChanged, $previousParentId): void {
            $destination = SiblingGroup::for($node, $newParent, $node->getKey());

            // Clamp rather than fail: the index came from ResolveSiblingPlacement
            // against this same group, so an out-of-range value can only mean the
            // group changed underneath us. Landing at the end is the safe answer.
            $index = max(0, min($position, count($destination)));

            array_splice($destination, $index, 0, [SiblingGroup::key($node->getKey())]);

            $node->setAttribute(TreeColumns::parent(), $newParentId);
            $node->setAttribute(TreeColumns::position(), $index);
            $node->save();

            $this->renumber($node, $destination);

            if ($originGroupChanged) {
                // ⚠️ The half of a cross-parent move that is easy to forget: the
                // group the node LEFT is now missing an index, and left alone it
                // holds a gap for ever.
                $this->renumber($node, $this->groupUnder($node, $previousParentId));
            }
        });

        // Fired after the write, exactly once per completed move (AGENTS.md R-011).
        // The package writes no audit record of its own — a host listens (R5).
        event(new NodeMoved(
            node: $node,
            previousParentId: $previousParentId,
            newParentId: $newParentId === null ? null : SiblingGroup::key($newParentId),
            position: $node->treePosition(),
        ));
    }

    /**
     * @param  list<int|string>  $orderedKeys
     */
    private function renumber(Model $model, array $orderedKeys): void
    {
        $positionColumn = TreeColumns::position();

        foreach ($orderedKeys as $index => $key) {
            $sibling = $model->newQuery()->find($key);

            if (! $sibling instanceof Model) {
                continue;
            }

            if ((int) $sibling->getAttribute($positionColumn) === $index) {
                continue;
            }

            $sibling->setAttribute($positionColumn, $index);
            $sibling->save();
        }
    }

    /**
     * @return list<int|string>
     */
    private function groupUnder(Model $model, int|string|null $parentId): array
    {
        $query = $model->newQuery()
            ->where(TreeColumns::parent(), $parentId)
            ->orderBy(TreeColumns::position())
            ->orderBy(TreeColumns::tiebreaker() ?? $model->getKeyName());

        /** @var list<int|string> $keys */
        $keys = array_values(array_map(SiblingGroup::key(...), $query->pluck($model->getKeyName())->all()));

        return $keys;
    }

    /**
     * ⚠️ Answered by walking UP from the destination, using only members the
     * `TreeNode` contract actually declares.
     *
     * Research R12 records `descendantsAndSelf()` from
     * `staudenmeir/laravel-adjacency-list` as the mechanism, and that dependency is
     * still required — `IsTreeNode` supplies those relationships to hosts. But
     * calling it *here* would mean this guard silently disappears for any host that
     * implements `TreeNode` without the trait, and a missing cycle guard fails in
     * the unsafe direction: it corrupts the tree rather than refusing.
     *
     * This is the same question asked from the other end. It owns no per-driver
     * SQL, which was R12's actual objection to hand-rolling the recursive CTE, and
     * it costs one query per level of depth on a tree an admin panel displays.
     * It also detects a PRE-EXISTING cycle rather than looping for ever on one.
     */
    private function guardAgainstCycle(Model&TreeNode $node, ?TreeNode $newParent): void
    {
        if ($newParent === null) {
            return;
        }

        $nodeKey = SiblingGroup::key($node->getKey());

        /** @var array<array-key, true> $seen */
        $seen = [];
        $cursor = $newParent;

        while ($cursor !== null) {
            $cursorKey = SiblingGroup::key($cursor->getKey());

            if ($cursorKey === $nodeKey) {
                throw CycleException::make();
            }

            if (isset($seen[$cursorKey])) {
                // The stored tree already contains a loop. Refusing is the only
                // safe answer, and it is better than spinning here for ever.
                throw CycleException::make();
            }

            $seen[$cursorKey] = true;

            $parentId = $cursor->treeParentId();
            $parent = $parentId === null ? null : $node->newQuery()->find($parentId);

            $cursor = $parent instanceof TreeNode ? $parent : null;
        }
    }
}

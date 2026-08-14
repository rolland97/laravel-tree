<?php

declare(strict_types=1);

namespace Rolland\Tree\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Support\SiblingGroup;
use Rolland\Tree\Support\TreeColumns;

/**
 * The same-parent case: rewrite one group's order to the given keys.
 */
final class ReorderSiblings
{
    /**
     * @param  list<int|string>  $orderedKeys  the COMPLETE group, in the desired order
     *
     * ⚠️ `$orderedKeys` are bare keys carrying no model class, so the model is
     * resolved from `$parent`. A ROOT-level group (`$parent === null`) therefore
     * cannot be addressed through this action — there is nothing to ask. That is a
     * gap in the v1 contract rather than an oversight in this body, and it is
     * named here instead of being worked around: the caller-facing entry point for
     * a root-level move is `PlaceNode`, which carries the node and so carries the
     * model. Widening this signature is a design amendment, not a commit
     * (`contracts/public-api.md`).
     */
    public function handle(?TreeNode $parent, array $orderedKeys): void
    {
        if ($orderedKeys === []) {
            return;
        }

        if (! $parent instanceof Model) {
            throw new InvalidArgumentException(
                'ReorderSiblings cannot address a root-level group in v1: the ordered keys carry no '
                .'model class and there is no parent to resolve one from. Use PlaceNode, which carries the node.'
            );
        }

        $normalised = array_map(SiblingGroup::key(...), $orderedKeys);

        DB::transaction(function () use ($parent, $normalised): void {
            $positionColumn = TreeColumns::position();

            foreach ($normalised as $index => $key) {
                $sibling = $parent->newQuery()
                    ->where(TreeColumns::parent(), $parent->getKey())
                    ->find($key);

                if (! $sibling instanceof Model) {
                    continue;
                }

                if ((int) $sibling->getAttribute($positionColumn) === $index) {
                    continue;
                }

                $sibling->setAttribute($positionColumn, $index);
                $sibling->save();
            }
        });

        event(new SiblingsReordered(
            parentId: SiblingGroup::key($parent->getKey()),
            orderedKeys: $normalised,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Rolland\Tree\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Exceptions\NotASiblingException;
use Rolland\Tree\Support\SiblingGroup;
use Rolland\Tree\Support\TreeColumns;

/**
 * The same-parent case: rewrite one group's order to the given keys.
 */
final class ReorderSiblings
{
    /**
     * @param  list<int|string>  $orderedKeys  the COMPLETE group, in the desired order
     * @param  class-string<Model&TreeNode>|null  $model  required only for a ROOT-level group
     *
     * ⚠️ `$orderedKeys` are bare keys carrying no model class, so the model is
     * normally inferred from `$parent`. A ROOT-level group has no parent to infer
     * from, which in v1 made this action unusable for an entire class of groups —
     * a public entry point that threw for roots.
     *
     * `$model` closes that gap. It is TRAILING and OPTIONAL on purpose: AGENTS.md
     * R-030 demands a RENAME when a signature's MEANING changes, because a stale
     * positional call would otherwise stay syntactically valid and silently mean
     * something else. Appending a parameter moves no existing position, so every
     * call written against the old signature keeps its exact meaning and no rename
     * is owed. If either of the first two ever changes meaning, rename the method.
     *
     * @throws InvalidArgumentException when a root-level group names no model
     * @throws NotASiblingException when a key is not a member of the named group
     */
    public function handle(?TreeNode $parent, array $orderedKeys, ?string $model = null): void
    {
        if ($orderedKeys === []) {
            return;
        }

        $prototype = $this->prototype($parent, $model);

        // ⚠️ `array_values` is load-bearing, not tidying (finding F22).
        //
        // `$orderedKeys` is DOCUMENTED `list<int|string>`, and nothing enforced it:
        // `array_map` preserves keys, and the write loop below uses the array KEY
        // as the position. So a caller who filtered a list before passing it — and
        // `array_filter` preserves keys — had its ORIGINAL indexes written as
        // positions, leaving the group non-contiguous in breach of R-009, and
        // `SiblingsReordered` carrying a keyed array that serialises to a JSON
        // object rather than the list its own docblock promises.
        //
        // A documented type the code does not honour is the same defect shape as
        // PA-2's authorization claim, and it is fixed here rather than defended
        // against by every caller.
        $normalised = array_values(array_map(SiblingGroup::key(...), $orderedKeys));

        // ⚠️ Resolve EVERY key before writing anything (finding F23).
        //
        // This loop used to sit inside the transaction and `continue` past a key it
        // could not find in the group. Skipping is not a gentler refusal: the write
        // below gives each member the index it held in the GIVEN list, so a skipped
        // key leaves that index unused and the group ends up NON-CONTIGUOUS
        // (R-009) — from a call that reported success.
        //
        // Resolved first, and refused before the first write, so a refusal leaves
        // the group exactly as it was. Same order `PlaceNode` uses for an
        // unreachable reference, and for the same reason.
        $siblings = [];

        foreach ($normalised as $key) {
            $sibling = $prototype->newQuery()
                ->where(TreeColumns::parent(), $parent?->getKey())
                ->find($key);

            if (! $sibling instanceof Model) {
                throw NotASiblingException::make();
            }

            $siblings[] = $sibling;
        }

        DB::transaction(function () use ($siblings): void {
            $positionColumn = TreeColumns::position();

            foreach ($siblings as $index => $sibling) {
                if ((int) $sibling->getAttribute($positionColumn) === $index) {
                    continue;
                }

                $sibling->setAttribute($positionColumn, $index);
                $sibling->save();
            }
        });

        event(new SiblingsReordered(
            parentId: $parent === null ? null : SiblingGroup::key($parent->getKey()),
            orderedKeys: $normalised,
        ));
    }

    /**
     * A model instance to query the group through.
     *
     * @param  class-string<Model&TreeNode>|null  $model
     */
    private function prototype(?TreeNode $parent, ?string $model): Model
    {
        if ($model === null) {
            if ($parent instanceof Model) {
                return $parent;
            }

            // ⚠️ Refused rather than guessed. Picking a model here would silently
            // reorder some other table's roots, which is far worse than an error.
            throw new InvalidArgumentException(
                'ReorderSiblings needs a $model to address a root-level group: the ordered keys carry no '
                .'model class and there is no parent to infer one from.'
            );
        }

        if (! is_a($model, Model::class, allow_string: true) || ! is_a($model, TreeNode::class, allow_string: true)) {
            throw new InvalidArgumentException(
                "ReorderSiblings was given [{$model}], which is not an Eloquent model implementing TreeNode."
            );
        }

        return new $model;
    }
}

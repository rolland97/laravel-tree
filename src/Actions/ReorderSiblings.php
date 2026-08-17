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
     */
    public function handle(?TreeNode $parent, array $orderedKeys, ?string $model = null): void
    {
        if ($orderedKeys === []) {
            return;
        }

        $prototype = $this->prototype($parent, $model);

        $normalised = array_map(SiblingGroup::key(...), $orderedKeys);

        DB::transaction(function () use ($parent, $prototype, $normalised): void {
            $positionColumn = TreeColumns::position();

            foreach ($normalised as $index => $key) {
                $sibling = $prototype->newQuery()
                    ->where(TreeColumns::parent(), $parent?->getKey())
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

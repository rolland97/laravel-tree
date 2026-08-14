<?php

declare(strict_types=1);

namespace Rolland\Tree\Actions;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Exceptions\UnreachableReferenceException;
use Rolland\Tree\Support\SiblingGroup;

/**
 * The rule this package exists for.
 *
 * Turns "place this node beside that sibling" into an index within the COMPLETE
 * destination group — while refusing any reference the actor could not have aimed
 * at.
 */
final class ResolveSiblingPlacement
{
    /**
     * @param  list<int|string>  $renderedSiblingIds  the destination group AS THE ACTOR SAW IT,
     *                                                in display order
     * @return int index within the complete group, the moved node excluded
     *
     * @throws UnreachableReferenceException
     */
    public function handle(
        Model&TreeNode $node,
        ?TreeNode $destinationParent,
        ?TreeNode $reference,
        SiblingPlacement $placement,
        array $renderedSiblingIds,
    ): int {
        $group = SiblingGroup::for($node, $destinationParent, $node->getKey());

        if (! $placement->needsReference()) {
            // LastChild names no neighbour, so there is nothing to refuse and no
            // index to get wrong. A reference supplied alongside it is ignored
            // rather than rejected — refusing would make needsReference() a lie.
            return count($group);
        }

        if ($reference === null) {
            throw UnreachableReferenceException::make();
        }

        $referenceKey = SiblingGroup::key($reference->getKey());

        // ⚠️ Refusal 3 of 3: the reference is the node being moved. Checked before
        // the group lookup because the group deliberately excludes the moved node,
        // so this would otherwise surface as the less accurate "not in group".
        if ($referenceKey === SiblingGroup::key($node->getKey())) {
            throw UnreachableReferenceException::make();
        }

        // ⚠️ Refusal 2 of 3: in the group, but never shown to the actor. This is
        // the authorization half of Principle II — the reference IS a real
        // sibling, the actor simply could not have aimed at it. Resolving it
        // anyway lets a tampered payload address a node privacy hides.
        //
        // Compared through the same normaliser as the group so a key arriving from
        // the driver as the string "3" still matches integer 3 (research R4).
        $rendered = array_map(SiblingGroup::key(...), $renderedSiblingIds);

        if (! in_array($referenceKey, $rendered, strict: true)) {
            throw UnreachableReferenceException::make();
        }

        // ⚠️ Refusal 1 of 3: not a member of the complete destination group.
        $index = array_search($referenceKey, $group, strict: true);

        if ($index === false) {
            throw UnreachableReferenceException::make();
        }

        return $placement === SiblingPlacement::Before ? $index : $index + 1;
    }
}

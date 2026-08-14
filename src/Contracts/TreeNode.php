<?php

declare(strict_types=1);

namespace Rolland\Tree\Contracts;

/**
 * What a host model must be able to answer.
 *
 * ⚠️ There is deliberately no visibility member here. Visibility is not a property
 * of a node; it is a property of a node AND an actor. Putting it on this interface
 * would invite a single global answer, and the package would then have a default —
 * which fails in the unsafe direction. It arrives as an argument instead
 * (constitution Principle III, AGENTS.md R-003).
 */
interface TreeNode
{
    /**
     * @return mixed
     */
    public function getKey();

    public function treeParentId(): int|string|null;

    public function treePosition(): int;

    /**
     * May this node receive children?
     *
     * ⚠️ The HOST decides. `IsTreeNode` deliberately does not implement this: a
     * default here would be the package deciding, and every host that forgot to
     * implement it would silently inherit that decision.
     */
    public function isValidTreeTarget(): bool;
}

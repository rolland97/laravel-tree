<?php

declare(strict_types=1);

namespace Rolland\Tree\Concerns;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Support\TreeColumns;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * Adjacency-list relationships plus the position mechanics, read from config.
 *
 * ⚠️ This trait deliberately does NOT implement `isValidTreeTarget()`. A default
 * there would be the package deciding whether a node may receive children, and
 * every host that forgot to implement it would silently inherit that decision.
 * A host implements it; PHP's abstract-member check is the reminder.
 *
 * ⚠️ Column names come from `config('tree.*')`, which is GLOBAL to the package in
 * v1 rather than per-model (data-model.md § Configuration). A host with two tree
 * models needing different column names is a named limitation, not a bug to work
 * around — it would be a MINOR amendment, not a patch.
 *
 * @mixin Model
 *
 * ⚠️ The ignore below is a fact about a library, not a silenced defect: PHPStan
 * analyses `src` and `config` (AGENTS.md R-031), and this trait's only consumers
 * are HOST models, which by definition live outside them. `staudenmeir` annotates
 * its own `HasRecursiveRelationships` identically for the same reason. Do not
 * "fix" it by widening the analysed paths — the consumers still would not be there.
 *
 * @phpstan-ignore trait.unused
 */
trait IsTreeNode
{
    use HasRecursiveRelationships;

    /**
     * Tell the adjacency-list package which column holds the parent reference.
     *
     * @return string
     */
    public function getParentKeyName()
    {
        return self::treeParentColumn();
    }

    public function treeParentId(): int|string|null
    {
        $value = $this->getAttribute(self::treeParentColumn());

        if ($value === null || $value === '') {
            return null;
        }

        return is_int($value) ? $value : (string) $value;
    }

    public function treePosition(): int
    {
        return (int) $this->getAttribute(self::treePositionColumn());
    }

    public static function treeParentColumn(): string
    {
        return TreeColumns::parent();
    }

    public static function treePositionColumn(): string
    {
        return TreeColumns::position();
    }

    /**
     * The second sort key. `null` falls back to the model's key.
     *
     * ⚠️ Not cosmetic. Positions in real data are neither unique nor dense —
     * legacy rows, rows written before this package was adopted, and rows in
     * groups no move has touched all collide. Without a tie-breaker two nodes
     * sharing a position would order arbitrarily by driver, and the index the
     * package resolves would mean something different from what the actor saw
     * (research R3).
     */
    public static function treeTiebreakerColumn(): ?string
    {
        return TreeColumns::tiebreaker();
    }
}

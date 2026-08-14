<?php

declare(strict_types=1);

namespace Rolland\Tree\Support;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;

/**
 * Reading a sibling group, in one place.
 *
 * ⚠️ Internal. Not part of the public surface (`contracts/public-api.md`).
 *
 * There is exactly one copy of the read order in this package, and it lives here.
 * Two copies would eventually disagree, and the whole point of resolving against
 * the complete group is that the index means the same thing to the package as the
 * order meant to the actor.
 */
final class SiblingGroup
{
    /**
     * Every child of `$parent`, EXCLUDING `$exclude`, in read order.
     *
     * Read order is: position ascending, then the configured tie-breaker
     * ascending (research R3).
     *
     * @return list<int|string>
     */
    public static function for(Model $model, ?TreeNode $parent, mixed $exclude = null): array
    {
        $query = $model->newQuery()
            ->where(TreeColumns::parent(), $parent?->getKey())
            ->orderBy(TreeColumns::position())
            ->orderBy(TreeColumns::tiebreaker() ?? $model->getKeyName());

        if ($exclude !== null) {
            $query->whereKeyNot($exclude);
        }

        /** @var list<int|string> $keys */
        $keys = array_values(array_map(self::key(...), $query->pluck($model->getKeyName())->all()));

        return $keys;
    }

    /**
     * Normalise a key for comparison.
     *
     * ⚠️ Research R4, and the reason this method exists at all. `pluck()->all()`
     * is typed `array<mixed>`, and a key arriving from the driver as the numeric
     * string "3" would never match integer 3 under a strict comparison — the move
     * would be refused as "reference not in group" for a perfectly valid
     * reference. Found by static analysis in the source application, not by a
     * test, which is why the cast is centralised here rather than repeated at each
     * comparison site where one copy could be forgotten.
     *
     * Comparisons stay STRICT. This normalises the values, not the comparison.
     */
    public static function key(mixed $key): int|string
    {
        if (is_int($key)) {
            return $key;
        }

        if (is_string($key) && $key !== '' && ctype_digit(ltrim($key, '-'))) {
            return (int) $key;
        }

        return is_scalar($key) ? (string) $key : '';
    }
}

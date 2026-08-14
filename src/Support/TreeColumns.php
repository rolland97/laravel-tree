<?php

declare(strict_types=1);

namespace Rolland\Tree\Support;

/**
 * The configured column names, resolved in ONE place.
 *
 * ⚠️ Internal. Not part of the public surface (`contracts/public-api.md`).
 *
 * ⚠️ The actions deliberately do NOT ask the model for its column names. The
 * `TreeNode` contract does not declare them, and adding them to it would be a
 * design amendment rather than a commit — but more importantly, configuration is
 * GLOBAL to the package in v1 (data-model.md § Configuration). Asking each model
 * would quietly turn it per-model, which is exactly the out-of-scope behaviour the
 * data model says to report as a limitation instead of working around.
 *
 * `IsTreeNode` reads through here too, so there is one answer rather than two that
 * eventually disagree.
 */
final class TreeColumns
{
    public static function parent(): string
    {
        return self::string('tree.parent_column', 'parent_id');
    }

    public static function position(): string
    {
        return self::string('tree.position_column', 'position');
    }

    /**
     * The second sort key. `null` means fall back to the model's key.
     *
     * Not cosmetic: positions in real data are neither unique nor dense, and
     * without a tie-breaker two nodes sharing a position order arbitrarily by
     * driver — so the index the package resolves would mean something different
     * from what the actor saw (research R3).
     */
    public static function tiebreaker(): ?string
    {
        $value = config('tree.tiebreaker');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function string(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}

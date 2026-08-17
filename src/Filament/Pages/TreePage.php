<?php

declare(strict_types=1);

namespace Rolland\Tree\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;

/**
 * An accessible drag-and-keyboard tree page. A host extends this.
 *
 * ⚠️ The body lives in `InteractsWithTree`, and this class is deliberately a thin
 * wrapper over it. A tree registered as a RESOURCE INDEX page must extend
 * `Filament\Resources\Pages\Page` to keep `route()`, and PHP has no second
 * inheritance slot — so that host uses the trait directly. One body, two hosts,
 * nothing to drift (package amendment PA-1).
 *
 * ⚠️ The host supplies `$model` and `visibleQuery()`. THE PACKAGE NEVER ADDS A
 * PRIVACY SCOPE — it owns the ordering rule, the host owns who may see what
 * (constitution Principle III, AGENTS.md R-003).
 *
 * @property-read string $treeSearch
 */
abstract class TreePage extends Page
{
    use InteractsWithTree;

    /**
     * ⚠️ Stays HERE rather than moving into the trait, and this is not a
     * stylistic choice. A trait property and a using class's own property with
     * different initial values is a FATAL composition error — so a trait carrying
     * `protected static string $model` would refuse to compose with every host
     * that names its model, which is all of them. A host reaching the tree through
     * the trait declares its own model however it likes; nothing in the trait
     * reads this.
     *
     * @var class-string<Model&TreeNode>
     */
    protected static string $model;

    /** @return class-string<Model&TreeNode> */
    public static function treeModel(): string
    {
        return static::$model;
    }
}

<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

/**
 * A composed host that has ALSO retired ordering (PA-19 × PA-18).
 *
 * ⚠️ The two amendments have to hold together, and that is not automatic: PA-18's
 * answer reaches the markup through the branch blade and the controller through the
 * `x-data` argument, and PA-19 moves the element that carries that argument into a
 * different file. A split that dropped the third argument would leave a page with no
 * handle whose keyboard still picked rows up — the exact half-state PA-18 exists to
 * prevent, and only a composed-AND-unordered host can catch it.
 */
final class ComposedUnorderedTreePage extends ComposedTreePage
{
    protected static ?string $slug = 'composed-unordered-tree';

    protected static ?string $title = 'Composed unordered tree';

    public function treeReorderEnabled(): bool
    {
        return false;
    }
}

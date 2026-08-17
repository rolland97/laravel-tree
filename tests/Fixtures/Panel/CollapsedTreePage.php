<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host whose branches start CLOSED (PA-8).
 *
 * ⚠️ The package's controller initialises `collapsed: {}` and `isExpanded()`
 * answers true for anything not explicitly closed, so every branch of every host
 * rendered OPEN with no way to say otherwise. That is not cosmetic: it decides what
 * the arrow keys traverse, what a screen reader walks, and how much of a large tree
 * is on the page at first paint (the consumer's adoption, T048).
 *
 * ⚠️ The children of a closed branch MUST still be in the DOM. They are members of
 * their group whether or not the actor has opened it, and removing them would make
 * the rendered set the client reports back to the server depend on what happened to
 * be open — which is the client-index defect constitution Principle II exists to
 * prevent, arriving by a different door.
 */
final class CollapsedTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'collapsed-tree';

    protected static ?string $title = 'Collapsed tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    public function treeBranchesStartCollapsed(): bool
    {
        return true;
    }
}

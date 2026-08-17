<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host whose NAVIGATION label and whose TREE's accessible name are different
 * strings (PA-7).
 *
 * ⚠️ The two are separate jobs. A navigation item answers "where am I going";
 * a `role="tree"` answers "what is this control". The first real consumer has had
 * both since before this package existed — *"Vendor categories"* in the sidebar and
 * *"Vendor category hierarchy"* on the tree — and the package named the tree with
 * `static::getNavigationLabel()`, so the ONLY way to change the tree's name was to
 * rename the navigation item (the consumer's adoption, T048).
 *
 * ⚠️ Deliberately a SEPARATE fixture from `CollapsedTreePage`. Driving two
 * unrelated slots from one fixture cannot catch a package that conflates them —
 * the same reasoning that keeps `$hideBravo` and `$moveAuthorization` apart in
 * `CategoryTreePage`.
 */
final class NamedTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'named-tree';

    /** ⚠️ The NAVIGATION label, and it must stay reachable and unchanged. */
    protected static ?string $title = 'Named tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    public function treeAccessibleName(): string
    {
        return 'The category hierarchy';
    }
}

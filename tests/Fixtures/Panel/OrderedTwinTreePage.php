<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * The ORDERED half of the PA-18 twin pair — a plain host that says nothing.
 *
 * ⚠️ It exists so that `UnorderedTreePage` can EXTEND it and override exactly one
 * method. C5–C8 say the ARIA, the expand/collapse and the search are unchanged by
 * PA-18, and the only way to assert "unchanged" honestly is to diff two pages that
 * differ in nothing else. Two independently written fixtures could drift into a
 * difference the diff would then blame on the slot — this pair cannot.
 *
 * ⚠️ Deliberately spare: no badges, no row actions, no leaf slot. Filament renders
 * its own ARIA into a button, and a fixture carrying host chrome would put that
 * chrome into the very attribute sets the C5 guard compares.
 */
class OrderedTwinTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'ordered-twin-tree';

    protected static ?string $title = 'Ordered twin tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }
}

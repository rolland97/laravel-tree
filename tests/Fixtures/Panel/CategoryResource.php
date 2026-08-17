<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A resource whose INDEX page is the tree — the shape the first real consumer has.
 *
 * ⚠️ This fixture exists to reproduce package-amendment PA-1. The consumer
 * application registers its tree as `'index' => TreeVendorCategories::route('/')`,
 * and `route()` is declared ONLY on `Filament\Resources\Pages\Page`. A host that
 * had to `extends TreePage` could not also be a resource page, so the resource's
 * index URL, `getUrl()`, the breadcrumb and the sub-navigation all went with it.
 */
final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $slug = 'categories';

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => TreeCategories::route('/'),
        ];
    }
}

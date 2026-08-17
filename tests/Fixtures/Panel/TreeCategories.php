<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A tree hosted on a RESOURCE INDEX page rather than a standalone panel page.
 *
 * ⚠️ The whole point of PA-1: this class extends Filament's resource page — so it
 * keeps `route()`, `getUrl()`, the breadcrumb and the sub-navigation — and gets the
 * tree from a TRAIT. PHP has no second inheritance slot, so before the extraction
 * this host could not exist.
 *
 * ⚠️ It deliberately does NOT declare `protected static string $model`. That
 * property stays on `TreePage`: a trait property and a using class's own property
 * with different initial values is a FATAL composition error, so a trait carrying
 * `$model` would break every host that names its model — which is all of them.
 */
final class TreeCategories extends Page
{
    use InteractsWithTree;

    protected static string $resource = CategoryResource::class;

    protected static ?string $title = 'Categories as a tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    /**
     * ⚠️ The collision PA-1 asks to be checked explicitly. `getHeaderActions()` is
     * `protected` on `Filament\Pages\Concerns\InteractsWithHeaderActions`, which
     * this page inherits, AND on the tree trait. A trait method beats an INHERITED
     * one, so the trait's wins and this slot is reached — asserted rather than
     * assumed in `tests/Bridge/InteractsWithTreeTest.php`.
     *
     * @return array<int, Action>
     */
    protected function headerActions(): array
    {
        return [
            Action::make('create-root-from-resource')
                ->label('New root from the resource page')
                ->action(fn () => null),
        ];
    }
}

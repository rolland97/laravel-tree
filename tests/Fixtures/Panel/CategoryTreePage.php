<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host page, written the way the quickstart tells a host to write one.
 *
 * ⚠️ `visibleQuery()` is the HOST's privacy scope and the package never adds one.
 * Note that it filters on `is_visible`, which is a DIFFERENT column from the
 * `is_active` that `isValidTreeTarget()` answers with: "may this actor see it"
 * and "may this receive children" are different questions, and a fixture that
 * used one column for both could not catch a package that conflated them.
 */
final class CategoryTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'category-tree';

    protected static ?string $title = 'Category tree';

    /** Set by a test to simulate the actor's scope changing between render and commit. */
    public static bool $hideBravo = false;

    protected function visibleQuery(): Builder
    {
        $query = Category::query()->where('is_visible', true);

        if (self::$hideBravo) {
            $query->where('name', '!=', 'Bravo');
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function badgesFor(Model $node): array
    {
        return ['badge-for-'.$node->name];
    }

    /**
     * @return array<int, Action>
     */
    protected function rowActions(Model $node): array
    {
        return [
            Action::make('inspect-'.$node->getKey())
                ->label('Inspect '.$node->name)
                ->action(fn () => null),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function headerActions(): array
    {
        return [
            Action::make('create-root')
                ->label('New root category')
                ->action(fn () => null),
        ];
    }

    protected function leafSlot(Model $node): ?View
    {
        if ($node->name !== 'Delta') {
            return null;
        }

        return view('tree::testing.leaf-slot', ['node' => $node]);
    }

    protected function confirmationFor(Model $node, ?TreeNode $newParent): ?string
    {
        // Only cross-parent moves need confirming here; a same-parent reorder
        // applies immediately. `null` means "apply it".
        if ($newParent !== null && (int) $newParent->getKey() !== (int) $node->treeParentId()) {
            return 'Moving '.$node->name.' will move everything underneath it.';
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host that records WHAT it was asked to authorize (PA-2).
 *
 * ⚠️ Separate from `CategoryTreePage` because that fixture requires confirmation
 * for cross-parent moves, and the question here is what the permission slot
 * RECEIVES — which must be answerable without a modal in the way.
 */
final class RecordingTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'recording-tree';

    protected static ?string $title = 'Recording tree';

    /** @var list<array{node: Model, parent: TreeNode|null}> */
    public static array $asked = [];

    public static function forgetAsked(): void
    {
        self::$asked = [];
    }

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    protected function authorizeTreeMove(Model $node, ?TreeNode $newParent): bool
    {
        self::$asked[] = ['node' => $node, 'parent' => $newParent];

        return true;
    }
}

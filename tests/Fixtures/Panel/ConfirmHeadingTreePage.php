<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host that supplies its OWN confirmation heading alongside the body (PA-6).
 *
 * ⚠️ Deliberately a SEPARATE fixture rather than a switch on `CategoryTreePage`.
 * That page's switches are process-global statics and leaked across files once
 * already (finding F37); a second page costs one line in `TestPanelProvider` and
 * cannot leak at all.
 *
 * `CategoryTreePage` keeps returning a plain string, so the two fixtures together
 * pin both halves of the amendment: the string form still renders the package's
 * generic heading, and the array form replaces it.
 */
final class ConfirmHeadingTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'confirm-heading-tree';

    protected static ?string $title = 'Confirm heading tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    /**
     * @return array{heading: string, message: string}|null
     */
    protected function confirmationFor(Model $node, ?TreeNode $newParent): ?array
    {
        if ($newParent !== null && (int) $newParent->getKey() !== (int) $node->treeParentId()) {
            return [
                'heading' => 'Make this branch private?',
                'message' => 'Moving '.$node->name.' will move everything underneath it.',
            ];
        }

        return null;
    }
}

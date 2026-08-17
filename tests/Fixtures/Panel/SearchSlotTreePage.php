<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host that searches a SECOND column the package knows nothing about (PA-5).
 *
 * ⚠️ Mirrors the first real consumer, which searches name OR `short_code` and
 * promises exactly that in its own placeholder copy (the consumer's adoption, research
 * F6). `matchesSearch()` was already `protected` and therefore overridable — but
 * it was not in the documented member table, so a host overriding it took a
 * private dependency on an internal that could move without a major version.
 */
final class SearchSlotTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'search-slot-tree';

    protected static ?string $title = 'Search slot tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    protected function matchesSearch(Model $node, string $term): bool
    {
        $needle = mb_strtolower($term);

        foreach (['name', 'short_code'] as $column) {
            $haystack = mb_strtolower((string) $node->getAttribute($column));

            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

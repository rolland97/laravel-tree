<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Illuminate\Database\Eloquent\Builder;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * A host that OVERRIDES the announcements and uses every token PA-4 documents.
 *
 * ⚠️ This fixture is the whole point of PA-4. The package's own sweep
 * (`tests/Core/HostOverridesCopyTest.php`) checks the package's lang file against
 * the package's own token list, so it cannot see this defect **by construction**:
 * the mismatch only exists once a HOST writes `:position` into a key the
 * controller never substitutes it for. A screen-reader user then hears the literal
 * text `":position"`, on a page where every assertion still passes.
 *
 * Every template here is deliberately prefixed `HOST ` so a test cannot pass
 * against the package's own wording by accident.
 */
final class AnnouncementTreePage extends TreePage
{
    protected static string $model = Category::class;

    protected static ?string $slug = 'announcement-tree';

    protected static ?string $title = 'Announcement tree';

    protected function visibleQuery(): Builder
    {
        return Category::query()->where('is_visible', true);
    }

    /**
     * @return array<string, string>
     */
    public function treeStrings(): array
    {
        return [
            'picked_up' => 'HOST picked up :name, :position of :total.',
            'moved' => 'HOST moved :name to :position of :total.',
            'put_down' => 'HOST put down :name at :position of :total.',
            'cancelled' => 'HOST cancelled :name, returned to :position of :total.',
            'already_first' => 'HOST :name is already first of :total.',
            'already_last' => 'HOST :name is already last of :total.',
            'abandoned' => 'HOST abandoned :name.',
            'refused' => 'HOST refused :name.',
            'only_child' => 'HOST :name is an only child.',
        ];
    }
}

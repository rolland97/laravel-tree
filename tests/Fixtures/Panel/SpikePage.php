<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Filament\Pages\Page;

/**
 * The R10 spike's "one trivial tree page" (T043).
 *
 * Deliberately NOT the real TreePage: the question this answers is whether a
 * PACKAGE can serve a panel to a browser driver at all, and whether that panel
 * serves compiled CSS. Building it on top of the real page would mean the spike
 * could fail for reasons that have nothing to do with the seam under test.
 */
final class SpikePage extends Page
{
    protected string $view = 'tree::spike';

    protected static ?string $title = 'R10 spike';

    protected static ?string $slug = 'spike';

    public static function getNavigationLabel(): string
    {
        return 'R10 spike';
    }
}

<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

/**
 * A host that composes the tree into its OWN layout (PA-19).
 *
 * ⚠️ It overrides `getView()` — the seam the package already offered — and its view
 * includes `tree::tree-content`. That include is what PA-19 adds: before it, the only
 * view the package shipped was a whole page, so a host needing a second region beside
 * the tree had to reproduce the package's inner markup and depend on internals the
 * README excludes from the public surface.
 *
 * ⚠️ Extends `OrderedTwinTreePage` so it differs from that page in the LAYOUT alone.
 * Any difference the composed guards find can then only have come from the split.
 */
class ComposedTreePage extends OrderedTwinTreePage
{
    protected static ?string $slug = 'composed-tree';

    protected static ?string $title = 'Composed tree';

    public function getView(): string
    {
        return 'tree::composed-page';
    }
}

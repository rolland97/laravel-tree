<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * I2, in a real browser — a server-side report must reach the LIVE REGION, not
 * only a toast a screen-reader user never sees.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);
    $this->only = Category::create(['name' => 'Solo', 'parent_id' => $this->parent->id, 'position' => 0]);
});

it('announces an only-child no-op in the live region', function () {
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    // Drop the sole child back onto its own parent: a reorder that cannot change
    // anything, which the spec says must be reported rather than silently taken.
    $page->drag(
        '[data-ltree-key="'.$this->only->id.'"] [data-ltree-handle]',
        '[data-ltree-key="'.$this->parent->id.'"]'
    );

    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." const t = document.querySelector('.ltree-live-region')?.textContent ?? '';"
        .' if (t.includes("only child")) return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect($page->script("document.querySelector('.ltree-live-region').textContent"))
        ->toContain('only child');
});

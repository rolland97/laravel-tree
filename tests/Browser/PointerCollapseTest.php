<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * C2 — expanding and collapsing with a POINTER.
 *
 * ⚠️ FR-024 and US2 acceptance 1 ("displayed with parents collapsible") belong to
 * US2, the pointer story. They shipped unmet: collapse existed only via
 * ArrowLeft/ArrowRight, added in US3, so a mouse-only user could not collapse
 * anything. Found by the T097 critique.
 *
 * ⚠️ The chevron stays `aria-hidden` and `tabindex="-1"` (spec FR-038, AGENTS.md
 * R-016): the row already announces its expanded state, and a labelled chevron
 * would announce it twice. Hiding a POINTER affordance from assistive technology
 * is correct precisely because the keyboard has an equivalent, better path.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
});

function chevronOf(int|string $key): string
{
    return '[data-ltree-key="'.$key.'"] [data-ltree-chevron]';
}

function expandedState(object $page, int|string $key): mixed
{
    return $page->script(
        "document.querySelector('[data-ltree-key=\"{$key}\"]').getAttribute('aria-expanded')"
    );
}

it('offers a chevron the pointer can actually activate', function () {
    visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));
});

it('collapses a branch when the chevron is clicked', function () {
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    expect(expandedState($page, $this->delta->id))->toBe('true');

    $page->click(chevronOf($this->delta->id));
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." if (document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded') === 'false') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(expandedState($page, $this->delta->id))->toBe('false');
});

it('hides the children of a branch collapsed with the pointer', function () {
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    $page->click(chevronOf($this->delta->id));
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." const c = document.querySelector('[data-ltree-children-of=\"{$this->delta->id}\"]');"
        .' if (c && getComputedStyle(c).display === "none") return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect($page->script(
        "getComputedStyle(document.querySelector('[data-ltree-children-of=\"{$this->delta->id}\"]')).display"
    ))->toBe('none');
});

it('expands it again on a second click', function () {
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    $page->click(chevronOf($this->delta->id));
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." if (document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded') === 'false') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    $page->click(chevronOf($this->delta->id));
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." if (document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded') === 'true') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(expandedState($page, $this->delta->id))->toBe('true');
});

it('does not collapse when the row itself is clicked', function () {
    // ⚠️ Collapsing on a row click would make every row action and every drag
    // start also toggle the branch underneath it.
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    $page->click('[data-ltree-key="'.$this->delta->id.'"] .ltree-row-name');
    $page->script('(async () => { await new Promise(r => setTimeout(r, 400)); return true; })()');

    expect(expandedState($page, $this->delta->id))->toBe('true');
});

it('keeps the chevron out of the accessibility tree', function () {
    // The row already announces aria-expanded; the chevron must not say it again.
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    $bad = $page->script(
        "(() => Array.from(document.querySelectorAll('[data-ltree-chevron]'))"
        .'.filter((c) => c.getAttribute("aria-hidden") !== "true" || c.getAttribute("tabindex") !== "-1").length)()'
    );

    expect($bad)->toBe(0);
});

it('reports no accessibility violations with a branch collapsed by pointer', function () {
    $page = visit('/admin/category-tree')->assertPresent(chevronOf($this->delta->id));

    $page->click(chevronOf($this->delta->id));
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." if (document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded') === 'false') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    $page->assertNoAccessibilityIssues();
});

<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;

/**
 * PA-7 and PA-8 in a REAL browser.
 *
 * ⚠️ The bridge tests prove the markup the server sent. They cannot prove the
 * CONTROLLER agrees: a page that renders `display: none` while `isExpanded()` still
 * answers true has a tree that flashes closed and springs open the moment Alpine
 * boots, and every server-side assertion passes. Both halves have to be checked
 * where they actually meet (AGENTS.md R-028).
 *
 * ⚠️ The tree's accessible name is asserted through **axe's own accname
 * implementation**, not by reading `aria-label` back. That is the same discipline
 * R-014 imposes on a ROW, and for the same reason: a test that reimplements the
 * algorithm agrees with itself rather than with a screen reader.
 */
beforeEach(function () {
    // Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

/**
 * Wait until the controller has actually booted.
 *
 * ⚠️ Without this every case below is a coin toss on load order, and the ones that
 * assert a CLOSED branch would pass against the server's markup alone — which is
 * precisely the half already covered elsewhere. `init()` adopts the first row, so a
 * roving tabindex of 0 is the observable proof that Alpine ran.
 */
function bootedTree(object $page): object
{
    $page->assertPresent('[data-ltree-key]');
    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        .' if (document.querySelector(\'[data-ltree-key][tabindex="0"]\')) return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    return $page;
}

function branchDisplay(object $page, int|string $key): mixed
{
    return $page->script(
        "(() => { const c = document.querySelector('[data-ltree-children-of=\"{$key}\"]');"
        .' return c === null ? null : getComputedStyle(c).display; })()'
    );
}

function branchExpanded(object $page, int|string $key): mixed
{
    return $page->script(
        "document.querySelector('[data-ltree-key=\"{$key}\"]')?.getAttribute('aria-expanded')"
    );
}

function waitForExpanded(object $page, int|string $key, string $state): void
{
    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." if (document.querySelector('[data-ltree-key=\"{$key}\"]')?.getAttribute('aria-expanded') === '{$state}') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );
}

/** Press a key on the focused row and give the controller a moment to react. */
function pressKeyOnFocused(object $page, string $key): void
{
    $page->script(
        '(() => { const el = document.activeElement;'
        ." el.dispatchEvent(new KeyboardEvent('keydown', { key: '{$key}', bubbles: true, cancelable: true })); })()"
    );
    $page->script('(async () => { await new Promise(r => setTimeout(r, 250)); return true; })()');
}

// ── PA-8, a host whose branches start closed ─────────────────────────────────

it('keeps every branch closed after the controller boots', function () {
    $page = bootedTree(visit('/admin/collapsed-tree'));

    expect(branchExpanded($page, $this->delta->id))->toBe('false');
    expect(branchDisplay($page, $this->delta->id))->toBe('none');
});

it('does not walk the arrow keys into a branch nobody has opened', function () {
    // ⚠️ The reason this is not a cosmetic default. Arrows traverse DISPLAYED rows,
    // so the initial state decides what the whole keyboard path visits.
    $page = bootedTree(visit('/admin/collapsed-tree'));

    $page->script('document.querySelector(\'[data-ltree-key][tabindex="0"]\')?.focus()');
    pressKeyOnFocused($page, 'ArrowDown');

    expect($page->script('document.activeElement?.dataset?.ltreeKey ?? null'))
        ->toBe((string) $this->alpha->id);
});

it('opens a closed branch when the chevron is clicked', function () {
    $page = bootedTree(visit('/admin/collapsed-tree'));

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    waitForExpanded($page, $this->delta->id, 'true');

    expect(branchExpanded($page, $this->delta->id))->toBe('true');
    expect(branchDisplay($page, $this->delta->id))->not->toBe('none');
});

it('opens a closed branch with ArrowRight before descending into it', function () {
    // ⚠️ The three-state read in `isExpanded()` is what makes this work: an
    // untouched key is closed for this host, so the first Right must EXPAND and stay
    // put rather than descend into rows that are not displayed.
    $page = bootedTree(visit('/admin/collapsed-tree'));

    $page->script('document.querySelector(\'[data-ltree-key][tabindex="0"]\')?.focus()');
    pressKeyOnFocused($page, 'ArrowRight');
    waitForExpanded($page, $this->delta->id, 'true');

    expect(branchExpanded($page, $this->delta->id))->toBe('true');
    expect($page->script('document.activeElement?.dataset?.ltreeKey ?? null'))
        ->toBe((string) $this->delta->id);

    pressKeyOnFocused($page, 'ArrowRight');

    expect($page->script('document.activeElement?.dataset?.ltreeKey ?? null'))
        ->toBe((string) $this->charlie->id);
});

it('closes an opened branch again, which an explicit false must not break', function () {
    // Reopening writes `collapsed[key] = false`, so the host default must not
    // override an actor's explicit choice in either direction.
    $page = bootedTree(visit('/admin/collapsed-tree'));

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    waitForExpanded($page, $this->delta->id, 'true');

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    waitForExpanded($page, $this->delta->id, 'false');

    expect(branchExpanded($page, $this->delta->id))->toBe('false');
});

it('still starts a default host open', function () {
    // ⚠️ REGRESSION guard, in the browser: "Migration: none" has to hold where the
    // controller runs, not only in the served html.
    $page = bootedTree(visit('/admin/category-tree'));

    expect(branchExpanded($page, $this->delta->id))->toBe('true');
    expect(branchDisplay($page, $this->delta->id))->not->toBe('none');
});

it('reports no accessibility violations with every branch closed', function () {
    bootedTree(visit('/admin/collapsed-tree'))->assertNoAccessibilityIssues();
});

// ── PA-7, a host whose tree is not named after its navigation item ───────────

it('announces the tree by the name the host gave it', function () {
    $page = bootedTree(visit('/admin/named-tree'));

    $name = $page->script(
        '(() => { axe.setup(document.documentElement);'
        ." try { const tree = document.querySelector('[role=\"tree\"]');"
        .' return axe.commons.text.accessibleTextVirtual(axe.utils.getNodeFromTree(tree)).trim();'
        .' } finally { axe.teardown(); } })()'
    );

    expect($name)->toBe('The category hierarchy');
});

it('does not name the tree after the navigation item', function () {
    $page = bootedTree(visit('/admin/named-tree'));

    expect($page->script("document.querySelector('[role=\"tree\"]')?.getAttribute('aria-label')"))
        ->not->toBe('Named tree');
});

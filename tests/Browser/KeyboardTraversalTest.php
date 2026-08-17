<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;

/**
 * US3 (T063–T069) — traverse and describe the tree without a mouse.
 *
 * ⚠️ Research R11, trap 1: `keys()` is focus-then-type, so a key pressed while an
 * expand is still settling lands on the PREVIOUSLY focused row and reads as a
 * product bug. Every key press below is followed by a wait for focus to arrive
 * where it was sent.
 */
beforeEach(function () {
    // Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

/** The key of the row that currently holds DOM focus. */
function focusedKey(object $page): mixed
{
    return $page->script('document.activeElement?.dataset?.ltreeKey ?? null');
}

/** Press a key on the focused row, then wait for focus to settle on `$expect`. */
function pressAndSettle(object $page, string $key, int|string|null $expect = null): void
{
    $page->script(
        '(() => { const el = document.activeElement;'
        ." el.dispatchEvent(new KeyboardEvent('keydown', { key: '{$key}', bubbles: true, cancelable: true })); })()"
    );

    if ($expect === null) {
        return;
    }

    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." if (document.activeElement?.dataset?.ltreeKey === '{$expect}') return true;"
        .' await new Promise(r => setTimeout(r, 25));'
        .' } return false; })()'
    );
}

/** Focus the tree by clicking its first row, the way a pointer user would arrive. */
function enterTree(object $page): object
{
    $page->assertPresent('[data-ltree-key]');
    $page->script("document.querySelector('[data-ltree-key][tabindex=\"0\"]')?.focus()");

    return $page;
}

/**
 * The accessible name a screen reader would announce for a row.
 *
 * ⚠️ Computed by AXE's own accname implementation, not by resolving
 * `aria-labelledby` here. A test that reimplemented the algorithm would agree with
 * itself rather than with a screen reader — and the defect this guards against
 * (`role="treeitem"` computing its name from its CONTENTS) is invisible to a
 * hand-rolled check that only looks at the attribute.
 *
 * `axe.setup()` is required: axe's virtual tree is torn down after a run, and
 * `getNodeFromTree()` returns null without it.
 */
function announcedName(object $page, int|string $key): mixed
{
    return $page->script(
        "(() => { const el = document.querySelector('[data-ltree-key=\"{$key}\"]');"
        .' axe.setup(document.documentElement);'
        .' try { return axe.commons.text.accessibleTextVirtual(axe.utils.getNodeFromTree(el)).trim(); }'
        .' finally { axe.teardown(); } })()'
    );
}

// ── T063 — the whole tree is ONE tab stop ────────────────────────────────────

it('exposes exactly one tab stop for the whole tree', function () {
    $page = enterTree(visit('/admin/category-tree'));

    expect($page->script("document.querySelectorAll('[data-ltree-key][tabindex=\"0\"]').length"))->toBe(1);
});

it('still exposes exactly one tab stop after traversing', function () {
    // ⚠️ The count must stay at 1 THROUGHOUT. A roving tabindex that adds a stop
    // without removing the old one turns a tree into a tab-trap.
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);
    pressAndSettle($page, 'ArrowDown', $this->bravo->id);

    expect($page->script("document.querySelectorAll('[data-ltree-key][tabindex=\"0\"]').length"))->toBe(1);
});

// ── T064 — arrows traverse displayed rows and DO NOT WRAP ────────────────────

it('moves down through the displayed rows in display order', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);

    expect(focusedKey($page))->toBe((string) $this->charlie->id);
});

it('moves back up through the displayed rows', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);
    pressAndSettle($page, 'ArrowUp', $this->delta->id);

    expect(focusedKey($page))->toBe((string) $this->delta->id);
});

it('does not wrap past the last row', function () {
    // Display order: Delta, Charlie, Bravo, Alpha.
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'End', $this->alpha->id);
    pressAndSettle($page, 'ArrowDown');

    expect(focusedKey($page))->toBe((string) $this->alpha->id);
});

it('does not wrap past the first row', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'Home', $this->delta->id);
    pressAndSettle($page, 'ArrowUp');

    expect(focusedKey($page))->toBe((string) $this->delta->id);
});

// ── T065 — Right expands then descends, Left collapses then ascends ─────────

it('collapses an expanded branch with Left before moving anywhere', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowLeft');

    expect(focusedKey($page))->toBe((string) $this->delta->id);
    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded')"
    ))->toBe('false');
});

it('descends into the first child with Right once the branch is open', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowRight', $this->charlie->id);

    expect(focusedKey($page))->toBe((string) $this->charlie->id);
});

it('expands a collapsed branch with Right before descending', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowLeft');
    pressAndSettle($page, 'ArrowRight');

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-expanded')"
    ))->toBe('true');
    expect(focusedKey($page))->toBe((string) $this->delta->id);
});

it('ascends to the parent with Left from a leaf', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);
    pressAndSettle($page, 'ArrowLeft', $this->delta->id);

    expect(focusedKey($page))->toBe((string) $this->delta->id);
});

it('jumps to the first and last displayed rows with Home and End', function () {
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'End', $this->alpha->id);
    expect(focusedKey($page))->toBe((string) $this->alpha->id);

    pressAndSettle($page, 'Home', $this->delta->id);
    expect(focusedKey($page))->toBe((string) $this->delta->id);
});

it('skips rows hidden inside a collapsed branch', function () {
    // Collapsed children are not displayed, so arrows must step over them.
    $page = enterTree(visit('/admin/category-tree'));

    pressAndSettle($page, 'ArrowLeft');
    pressAndSettle($page, 'ArrowDown', $this->alpha->id);

    expect(focusedKey($page))->toBe((string) $this->alpha->id);
});

// ── T066 — the announced NAME, asserted directly ────────────────────────────

it('announces a row by its own name and nothing else', function () {
    // ⚠️ THE assertion the source application omitted while shipping four CORRECT
    // aria-* assertions. `role="treeitem"` computes its name from its CONTENTS, so
    // an unlabelled row announces its badges, every action label and — expanded —
    // its entire subtree. No automated check sees a fault, because a name exists.
    //
    // Computed by axe's own accname implementation, not by resolving
    // aria-labelledby here: a test that reimplemented the algorithm would agree
    // with itself rather than with a screen reader.
    $page = enterTree(visit('/admin/category-tree'));
    $page->assertNoAccessibilityIssues();

    $name = announcedName($page, $this->delta->id);

    expect($name)->toBe('Delta');
});

it('does not let a badge leak into the announced name', function () {
    $page = enterTree(visit('/admin/category-tree'));
    $page->assertNoAccessibilityIssues();

    $name = announcedName($page, $this->delta->id);

    expect($name)->not->toContain('badge-for-');
});

it('does not let a row action label leak into the announced name', function () {
    $page = enterTree(visit('/admin/category-tree'));
    $page->assertNoAccessibilityIssues();

    $name = announcedName($page, $this->delta->id);

    expect($name)->not->toContain('Inspect');
});

it('does not let an expanded subtree leak into the announced name', function () {
    // The exact defect: an expanded parent announcing every descendant.
    $page = enterTree(visit('/admin/category-tree'));
    $page->assertNoAccessibilityIssues();

    $name = announcedName($page, $this->delta->id);

    expect($name)->not->toContain('Charlie');
    expect($name)->not->toContain('Bravo');
});

// ── T067 — counts describe what was RENDERED ────────────────────────────────

it('counts only the rendered siblings in the set size', function () {
    // ⚠️ T070 says to DISTRUST this one: if it passes immediately, the guard is
    // measuring something downstream of the real guarantee. It is therefore scoped
    // to a SEARCH-FILTERED state, where the rendered set and the true group
    // genuinely differ — the equivalent guard in the source application could not
    // fail, because privacy was filtered upstream of the count.
    $page = enterTree(visit('/admin/category-tree'));

    // Unfiltered: Delta's children are Charlie and Bravo.
    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->charlie->id}\"]').getAttribute('aria-setsize')"
    ))->toBe('2');

    $page->type('input[type="search"]', 'Charlie');
    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." if (!document.querySelector('[data-ltree-key=\"{$this->bravo->id}\"]')) return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    // Bravo is no longer rendered, so the set size must describe ONE child — not
    // the two the group really holds.
    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->charlie->id}\"]')?.getAttribute('aria-setsize')"
    ))->toBe('1');
});

it('numbers a row within the rendered siblings, not the true group', function () {
    $page = enterTree(visit('/admin/category-tree'));

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->bravo->id}\"]').getAttribute('aria-posinset')"
    ))->toBe('2');
});

// ── T068 — one element carries role, tabindex and every aria-* ──────────────

it('puts role, tabindex and every aria property on the same focusable element', function () {
    // ⚠️ Constitution Principle IV / AGENTS.md R-013. Split across a wrapper and
    // an inner element, a screen reader announces one and focuses the other.
    $page = enterTree(visit('/admin/category-tree'));

    $split = $page->script(
        "(() => { const rows = Array.from(document.querySelectorAll('[role=\"treeitem\"]'));"
        .' return rows.filter(r => !(r.hasAttribute("tabindex")'
        .' && r.hasAttribute("aria-level")'
        .' && r.hasAttribute("aria-posinset")'
        .' && r.hasAttribute("aria-setsize")'
        .' && r.hasAttribute("aria-labelledby"))).length; })()'
    );

    expect($split)->toBe(0);
});

it('marks the chevron decorative rather than labelling it', function () {
    // ⚠️ Spec FR-038 / R-016. The row already announces its expanded state; a
    // labelled chevron would announce it a second time.
    $page = enterTree(visit('/admin/category-tree'));

    $bad = $page->script(
        "(() => { const c = Array.from(document.querySelectorAll('.ltree-chevron'));"
        .' return c.filter(x => x.getAttribute("aria-hidden") !== "true" || x.getAttribute("tabindex") !== "-1").length; })()'
    );

    expect($bad)->toBe(0);
});

// ── T069 — axe, in both schemes ─────────────────────────────────────────────

it('reports zero accessibility violations while traversing, in light mode', function () {
    $page = enterTree(visit('/admin/category-tree')->inLightMode());

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);

    $page->assertNoAccessibilityIssues();
});

it('reports zero accessibility violations while traversing, in dark mode', function () {
    $page = enterTree(visit('/admin/category-tree')->inDarkMode());

    pressAndSettle($page, 'ArrowDown', $this->charlie->id);

    $page->assertNoAccessibilityIssues();
});

// ── T076 — the empty-accessible-name trap ───────────────────────────────────

it('renders no control with an EMPTY accessible name', function () {
    // ⚠️ PKG-01 § Traps. Filament's own collapsible-section component ships
    // `aria-label=""`, which is a critical violation a page inherits merely by
    // using it. This tree page uses no collapsible section today — so this guard
    // is written to catch the day one is added, rather than to fix a defect that
    // is currently present.
    //
    // An EMPTY aria-label is worse than none: it overrides the name the element
    // would otherwise have computed, leaving the control anonymous.
    $page = enterTree(visit('/admin/category-tree'));

    $empty = $page->script(
        "(() => Array.from(document.querySelectorAll('[aria-label]'))"
        .'.filter((el) => el.getAttribute("aria-label").trim() === "")'
        .'.map((el) => el.tagName + "." + el.className).slice(0, 5))()'
    );

    expect($empty)->toBe([]);
});

it('gives every focusable control in the tree an accessible name', function () {
    $page = enterTree(visit('/admin/category-tree'));
    $page->assertNoAccessibilityIssues();

    $anonymous = $page->script(
        '(() => { axe.setup(document.documentElement);'
        ." try { const root = document.querySelector('.ltree-root');"
        .' return Array.from(root.querySelectorAll("button, a[href], [role=treeitem]"))'
        .'.filter((el) => el.getAttribute("aria-hidden") !== "true")'
        .'.filter((el) => !axe.commons.text.accessibleTextVirtual(axe.utils.getNodeFromTree(el)).trim())'
        .'.map((el) => el.tagName + "." + el.className).slice(0, 5);'
        .' } finally { axe.teardown(); } })()'
    );

    expect($anonymous)->toBe([]);
});

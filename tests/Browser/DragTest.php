<?php

declare(strict_types=1);

use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * T053/T054 — the pointer story, driven by a real browser and a real gesture.
 *
 * ⚠️ Every assertion reads the STORED ROWS, never the rendered list. A test
 * comparing a page against the same accessor the page rendered from cannot see a
 * defect inside that accessor (AGENTS.md R-024).
 *
 * ⚠️ A note on what a drop AIMS at. The controller derives the placement from the
 * POINTER POSITION within a row — top quarter means "before this row", bottom
 * quarter "after it", the middle half "inside it". The browser driver's
 * `drag()` can only aim at an element's CENTRE, so every gesture below lands in
 * the nest band. Before/After are therefore exercised through the same controller
 * function with real geometry rather than through a synthesised gesture, and that
 * limitation is named here rather than papered over with a weaker assertion.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    // Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'position' => 2]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 3]);
});

function ltreeRow(int|string $key): string
{
    return '[data-ltree-key="'.$key.'"]';
}

function ltreeHandle(int|string $key): string
{
    return ltreeRow($key).' [data-ltree-handle]';
}

/**
 * Wait until the browser has re-rendered `$key` as the last child of `$parentKey`.
 *
 * ⚠️ This is a WAIT, not an assertion. Nothing is proved by it — every claim in
 * this file is made against the stored rows (AGENTS.md R-024). It exists because
 * `assertMissing()` on an element that never appears returns IMMEDIATELY, so a
 * test that only asserted the absence of a confirmation raced the Livewire round
 * trip and read the database before the move had landed. That race made a working
 * drag look broken.
 */
function waitForLastChild(object $page, int|string $parentKey, int|string $key): void
{
    $page->script(
        '(async () => { for (let i = 0; i < 100; i++) {'
        ." const rows = Array.from(document.querySelectorAll('[data-ltree-key][data-ltree-parent=\"{$parentKey}\"]'));"
        ." if (rows.length && rows[rows.length - 1].dataset.ltreeKey === '{$key}') return true;"
        .' await new Promise(r => setTimeout(r, 50));'
        .' } return false; })()'
    );
}

it('queues a confirmation rather than writing, when the host asks for one', function () {
    // ⚠️ The gesture completes and the tree is UNCHANGED. Nothing is applied until
    // the host's confirmation is answered (spec FR-025).
    $before = Stored::order(null);

    visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeHandle($this->alpha->id), ltreeRow($this->delta->id))
        ->assertPresent('[data-ltree-confirm]');

    expect(Stored::order(null))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

it('nests the node once the confirmation is accepted', function () {
    visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeHandle($this->alpha->id), ltreeRow($this->delta->id))
        ->assertPresent('[data-ltree-confirm]')
        ->click('[data-ltree-confirm-submit]')
        ->assertMissing('[data-ltree-confirm]');

    expect($this->alpha->fresh()->parent_id)->toBe($this->delta->id);
});

it('leaves the tree untouched when the confirmation is cancelled', function () {
    $before = Stored::order(null);

    visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeHandle($this->alpha->id), ltreeRow($this->delta->id))
        ->assertPresent('[data-ltree-confirm]')
        ->click('[data-ltree-confirm-cancel]')
        ->assertMissing('[data-ltree-confirm]');

    expect(Stored::order(null))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

it('applies a same-parent move immediately, with no confirmation', function () {
    // Charlie and Bravo already live under Delta, so dropping Bravo into Delta is
    // a reorder within its own group — the fixture asks for no confirmation.
    $this->charlie->update(['parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo->update(['parent_id' => $this->delta->id, 'position' => 1]);

    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->charlie->id))
        ->drag(ltreeHandle($this->charlie->id), ltreeRow($this->delta->id))
        ->assertMissing('[data-ltree-confirm]');

    waitForLastChild($page, $this->delta->id, $this->charlie->id);

    // LastChild of Delta: Charlie moves from first to last.
    expect(Stored::order($this->delta->id))->toBe([$this->bravo->id, $this->charlie->id]);
});

it('leaves the affected group contiguous after a pointer move', function () {
    // Its own case, never chained to the ordering assertion above — a chain stops
    // at the first failure, so this could otherwise never be watched failing on
    // its own (quickstart.md § SC-003).
    $this->charlie->update(['parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo->update(['parent_id' => $this->delta->id, 'position' => 1]);

    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->charlie->id))
        ->drag(ltreeHandle($this->charlie->id), ltreeRow($this->delta->id))
        ->assertMissing('[data-ltree-confirm]');

    waitForLastChild($page, $this->delta->id, $this->charlie->id);

    expect(Stored::positions($this->delta->id))->toBe([0, 1]);
});

it('does not start a drag from anywhere but the handle', function () {
    // ⚠️ Spec FR-023 / AGENTS.md R-022. If a drag could begin from the row body,
    // activating a row action would become a move.
    $before = Stored::order(null);

    visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeRow($this->alpha->id).' .ltree-row-name', ltreeRow($this->delta->id))
        ->assertMissing('[data-ltree-confirm]');

    expect(Stored::order(null))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

it('produces exactly the order the equivalent PlaceNode call produces', function () {
    // The claim US2 has to make: the pointer is a CALLER of the placement rule,
    // not a second implementation of it.
    $this->charlie->update(['parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo->update(['parent_id' => $this->delta->id, 'position' => 1]);

    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->charlie->id))
        ->drag(ltreeHandle($this->charlie->id), ltreeRow($this->delta->id))
        ->assertMissing('[data-ltree-confirm]');

    waitForLastChild($page, $this->delta->id, $this->charlie->id);

    $viaPointer = Stored::order($this->delta->id);

    // Put it back, then make the identical move through the public API.
    $this->charlie->update(['parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo->update(['parent_id' => $this->delta->id, 'position' => 1]);

    app(PlaceNode::class)->handle(
        $this->charlie->fresh(),
        $this->delta,
        null,
        SiblingPlacement::LastChild,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(Stored::order($this->delta->id))->toBe($viaPointer);
});

it('keeps siblings the search hid in their relative order after a drag', function () {
    // ⚠️ T054, and the shape of the 071 defect. Quick search removes rows from the
    // DOM; a controller that sent only what it displayed, or a server that
    // renumbered only that subset, would shuffle the rows nobody could see.
    $this->charlie->update(['parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo->update(['parent_id' => $this->delta->id, 'position' => 1]);
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 2,
    ]);

    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->charlie->id))
        ->type('input[type="search"]', 'Charlie')
        ->assertDontSee('Aardvark');

    $page->drag(ltreeHandle($this->charlie->id), ltreeRow($this->delta->id))
        ->assertMissing('[data-ltree-confirm]');

    waitForLastChild($page, $this->delta->id, $this->charlie->id);

    // Aardvark was never rendered, so the actor could not have aimed at it — and
    // it must still be a member of the group, in a contiguous sequence.
    expect(Stored::positions($this->delta->id))->toBe([0, 1, 2]);
    expect(Stored::order($this->delta->id))->toContain($hidden->id);
});

// ── F39 — the confirmation is an alertdialog, so focus must ENTER it ─────────

it('moves focus into the confirmation when it appears', function () {
    // ⚠️ Measured in a real consumer panel first: the confirmation opened as
    // `role="alertdialog" aria-modal="true"`, and `document.activeElement` was
    // still the drag handle of the row that had just been dragged. A keyboard
    // actor had to tab forward blind to reach "Move it", and a screen-reader actor
    // was told nothing — the live region still held the PREVIOUS announcement
    // (the consumer's adoption, T057).
    //
    // ⚠️ An `alertdialog` that never receives focus is announced only by the AT
    // that happens to volunteer it, which is exactly the support that varies. The
    // markup was already correct; nothing moved focus to it.
    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeHandle($this->alpha->id), ltreeRow($this->delta->id))
        ->assertPresent('[data-ltree-confirm]');

    $inside = $page->script(
        "(() => { const dialog = document.querySelector('[data-ltree-confirm]');"
        .' return dialog.contains(document.activeElement); })()'
    );

    expect($inside)->toBeTrue();
});

it('leaves focus somewhere useful after the confirmation is answered', function () {
    // ⚠️ The other half, and the one that is easy to lose: taking focus into a
    // region that is about to be removed must not drop the actor at `<body>`.
    $page = visit('/admin/category-tree')
        ->assertPresent(ltreeRow($this->delta->id))
        ->drag(ltreeHandle($this->alpha->id), ltreeRow($this->delta->id))
        ->assertPresent('[data-ltree-confirm]')
        ->click('[data-ltree-confirm-cancel]')
        ->assertMissing('[data-ltree-confirm]');

    $landed = $page->script('document.activeElement === document.body');

    expect($landed)->toBeFalse();
});

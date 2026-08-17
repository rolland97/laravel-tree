<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\MoveCounter;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * US4 (T077–T085) — reorder from the keyboard, with announcements.
 *
 * ⚠️ Three of these exist because the equivalent assertion was MISSING when the
 * source application shipped: T079 (`put_down` had no assertion at all, and only
 * its critique caught it) and T082 (`already_last`, the second omission the
 * critique found).
 *
 * ⚠️ Announcements are read from the live region's rendered text — what a screen
 * reader would be handed. That is evidence for these criteria and explicitly NOT
 * for SC-011: a region's contents prove the DATA a reader receives, never that the
 * announcements work as heard sentences (AGENTS.md R-018).
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    MoveCounter::reset();

    // One parent with three children, positional order the reverse of alphabetical.
    $this->root = Category::create(['name' => 'Root', 'position' => 0]);
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->root->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->root->id, 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->root->id, 'position' => 2]);
});

function announcement(object $page): mixed
{
    return $page->script("document.querySelector('.ltree-live-region')?.textContent?.trim() ?? ''");
}

function focusRowByKey(object $page, int|string $key): void
{
    $page->assertPresent('[data-ltree-key]');
    $page->script("document.querySelector('[data-ltree-key=\"{$key}\"]').focus()");
}

function sendKey(object $page, string $key): void
{
    $page->script(
        '(() => { const el = document.activeElement;'
        ." el.dispatchEvent(new KeyboardEvent('keydown', { key: '{$key}', bubbles: true, cancelable: true })); })()"
    );
}

/** Wait until the live region says something, so assertions do not race the DOM. */
function settleAnnouncement(object $page): void
{
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." const t = document.querySelector('.ltree-live-region')?.textContent?.trim();"
        .' if (t) return t;'
        .' await new Promise(r => setTimeout(r, 25)); } return null; })()'
    );
}

// ── T077 — pick up ───────────────────────────────────────────────────────────

it('announces a pick-up', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('Picked up Charlie');
});

it('marks the held row as grabbed while it is carried', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->charlie->id}\"]').getAttribute('aria-grabbed')"
    ))->toBe('true');
});

// ── T078 — moving a held node persists NOTHING ──────────────────────────────

it('announces each new position while the node is held', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('position 1 of 3');
});

it('writes NOTHING to the database while a node is merely held and moved', function () {
    // ⚠️ T087. Held state lives client-side, and there is NO server call until the
    // drop. A call per arrow press would write one audit row per keystroke for what
    // the user thinks of as a single move.
    $before = Stored::order($this->root->id);

    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);

    // ⚠️ An explicit grace period, because this guard asserts that NOTHING
    // happened and therefore has no signal to wait for. Without it the assertion
    // can outrun a Livewire round trip that should never have been made, and the
    // guard passes against a package calling the server on every arrow press —
    // which is exactly what a mutation showed it doing.
    $page->script('(async () => { await new Promise(r => setTimeout(r, 700)); return true; })()');

    expect(Stored::order($this->root->id))->toBe($before);
    expect(MoveCounter::$moved)->toBe(0);
});

// ── T079 — put down (the assertion the source application never wrote) ──────

it('commits the move when the node is put down', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'Enter');

    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." const rows = Array.from(document.querySelectorAll('[data-ltree-parent=\"{$this->root->id}\"]'));"
        ." if (rows[0]?.dataset.ltreeKey === '{$this->charlie->id}') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(Stored::order($this->root->id))
        ->toBe([$this->charlie->id, $this->delta->id, $this->bravo->id]);
});

it('announces completion when the node is put down', function () {
    // ⚠️ The source application shipped `put_down` with NO assertion at all.
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'Enter');

    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." const t = document.querySelector('.ltree-live-region')?.textContent ?? '';"
        .' if (t.includes("Moved")) return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(announcement($page))->toContain('Moved Charlie');
});

it('leaves the row no longer marked as grabbed after a put-down', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'Enter');
    settleAnnouncement($page);

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->charlie->id}\"]')?.getAttribute('aria-grabbed')"
    ))->toBeNull();
});

// ── T084 — exactly ONE event for a completed interaction ────────────────────

it('fires exactly one reorder event however many keystrokes produced it', function () {
    // ⚠️ Spec FR-041 / AGENTS.md R-011.
    //
    // ⚠️ Counts `SiblingsReordered` since PA-3, and asserts `NodeMoved` did NOT
    // fire. A keyboard reorder never leaves its group, so it is a reorder — and
    // recording it as a move was what left `SiblingsReordered` with no producer
    // and cost a host's audit trail the written order. The guarantee is unchanged
    // and now proved on the correct event, through a real browser.
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->bravo->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'Enter');

    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." const rows = Array.from(document.querySelectorAll('[data-ltree-parent=\"{$this->root->id}\"]'));"
        ." if (rows[0]?.dataset.ltreeKey === '{$this->bravo->id}') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(MoveCounter::$reordered)->toBe(1);
    expect(MoveCounter::$moved)->toBe(0);
});

// ── T080 — cancel ───────────────────────────────────────────────────────────

it('leaves the tree unchanged when a held move is cancelled', function () {
    $before = Stored::order($this->root->id);

    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'Escape');
    settleAnnouncement($page);

    expect(Stored::order($this->root->id))->toBe($before);
    expect(MoveCounter::$moved)->toBe(0);
});

it('announces a cancellation', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'Escape');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('Cancelled');
});

// ── T081 — focus leaves the tree mid-hold ───────────────────────────────────

it('announces abandonment and persists nothing when focus leaves mid-hold', function () {
    $before = Stored::order($this->root->id);

    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    // Move focus right out of the tree, the way Tab would.
    $page->script("document.querySelector('input[type=\"search\"]').focus()");
    $page->script(
        '(async () => { for (let i = 0; i < 60; i++) {'
        ." const t = document.querySelector('.ltree-live-region')?.textContent ?? '';"
        .' if (t.includes("abandoned")) return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    expect(announcement($page))->toContain('abandoned');
    expect(Stored::order($this->root->id))->toBe($before);
    expect(MoveCounter::$moved)->toBe(0);
});

// ── T082 — boundaries, INCLUDING already-last ───────────────────────────────

it('announces that a node is already first', function () {
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->delta->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('already first');
});

it('announces that a node is already last', function () {
    // ⚠️ The SECOND omission the source application's critique found. `already_last`
    // shipped unasserted.
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->bravo->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowDown');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('already last');
});

it('announces that a node is an only child', function () {
    $solo = Category::create(['name' => 'Solo', 'position' => 1]);
    $only = Category::create(['name' => 'Only', 'parent_id' => $solo->id, 'position' => 0]);

    $page = visit('/admin/category-tree');
    focusRowByKey($page, $only->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowDown');
    settleAnnouncement($page);

    expect(announcement($page))->toContain('only child');
});

// ── T085 — the announcement survives a re-render we did not initiate ────────

it('keeps the announcement through a re-render the package did not initiate', function () {
    // ⚠️ The live region's content is CLIENT state the server knows nothing about.
    // A re-render wipes it unless the region is excluded from morphing, and a host
    // panel polling a notification bell every 30 seconds does exactly this.
    //
    // ⚠️ Research R11, trap 2: `$wire.$refresh()` returns a promise that NEVER
    // resolves when awaited in a page evaluation — the source application's run
    // hung for fifteen minutes. It is voided here, never awaited.
    $page = visit('/admin/category-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    $before = announcement($page);
    expect($before)->toContain('Picked up');

    $page->script(
        "(() => { const el = document.querySelector('.ltree-root');"
        ." const id = el.closest('[wire\\\\:id]')?.getAttribute('wire:id');"
        .' if (id && window.Livewire) { void window.Livewire.find(id).$refresh(); } })()'
    );

    $page->script('(async () => { await new Promise(r => setTimeout(r, 800)); return true; })()');

    expect(announcement($page))->toBe($before);
});

// ── T083 — an unauthorised pick-up ──────────────────────────────────────────

it('refuses a pick-up the host does not permit, and begins no hold', function () {
    // The keyboard refuses early as a COURTESY; the real guard is placeNode's
    // re-check on the committing call, which the bridge suite proves separately.
    //
    // ⚠️ `data-ltree-immovable`, not `data-ltree-locked` (PA-13). This test used to
    // set the LOCKED flag, which answers "may this node receive children" — so the
    // package's own guard was asserting the conflation rather than catching it. The
    // behaviour it checks is unchanged; the attribute it drives is the one that
    // actually means "this actor may not move this".
    $page = visit('/admin/category-tree');

    $locked = $page->script(
        "(() => { const row = document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]');"
        .' row.setAttribute("data-ltree-immovable", "true"); row.focus(); return true; })()'
    );

    sendKey($page, ' ');
    settleAnnouncement($page);

    expect($locked)->toBeTrue();
    expect(announcement($page))->toContain('cannot move');
    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->delta->id}\"]').getAttribute('aria-grabbed')"
    ))->toBeNull();
});

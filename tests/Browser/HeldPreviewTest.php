<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * PA-15 and PA-16 — what a held node looks like, and what leaving does to it.
 *
 * PA-15: the arrow keys announced a new position and MOVED NOTHING. A screen-reader
 * user heard "position 1 of 3"; anyone watching the screen saw the row sit still
 * until Enter. The two audiences were told different things by the same keystroke,
 * and a sighted keyboard user had no way to tell whether the key had worked.
 *
 * PA-16: `Tab` did not abandon a hold. The tree is documented as one tab stop, but a
 * host's row actions are real focusable buttons INSIDE it — so Tab moves focus from
 * the row to its own edit button, `onTreeFocusOut()` sees focus still inside
 * `[role="tree"]`, and the node stays held while the actor has visibly left it.
 * `tests/Browser/KeyboardTraversalTest.php` counts `[data-ltree-key][tabindex="0"]`
 * for its one-tab-stop claim, which counts ROWS and cannot see this.
 *
 * ⚠️ Nothing here asserts a WRITE from a preview. Every stored-row check is that
 * nothing was written, which is the invariant the preview must not break (T087: a
 * call per arrow press would file one audit row per keystroke).
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;

    // Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->parent->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'parent_id' => $this->parent->id, 'position' => 2]);
});

/**
 * ⚠️ Restored HERE, not only set in `beforeEach()`, because two cases below flip
 * `$moveAuthorization` MID-TEST and the property is a process-global static.
 *
 * Setting it at the start of a file only protects that file: with
 * `executionOrder="random"` (R-029) the next file to run inherits whatever the last
 * one left, and a `deny` leaking out of here turned two of `KeyboardReorderTest`'s
 * commits into refusals — a green suite in one seed and two failures in another,
 * reported against code that had not changed. A test that mutates shared state puts
 * it back.
 */
afterEach(function () {
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;
    CategoryTreePage::$hideBravo = false;
});

/** The rendered order of a group, read from the DOM. */
function heldPreviewRenderedOrder(object $page, int|string $parentKey): mixed
{
    return $page->script(
        '(() => Array.from(document.querySelectorAll('
        ."'[data-ltree-key][data-ltree-parent=\"{$parentKey}\"] .ltree-row-name'"
        .')).map((el) => el.textContent.trim()).join("|"))()'
    );
}

function heldPreviewSend(object $page, string $key): void
{
    $page->script(
        '(() => { document.activeElement?.dispatchEvent('
        ."new KeyboardEvent('keydown', { key: '{$key}', bubbles: true, cancelable: true })); })()"
    );
    $page->script('(async () => { await new Promise(r => setTimeout(r, 350)); return true; })()');
}

function heldPreviewFocus(object $page, int|string $key): void
{
    $page->assertPresent('[data-ltree-key="'.$key.'"]');
    $page->script("document.querySelector('[data-ltree-key=\"{$key}\"]').focus()");
}

function heldPreviewAnnouncement(object $page): mixed
{
    return $page->script("(document.querySelector('.ltree-live-region')?.textContent ?? 'NO-REGION').trim()");
}

// ── PA-15 — the row moves while it is held ───────────────────────────────────

it('moves the held row on screen as the actor previews', function () {
    $page = visit('/admin/category-tree');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Bravo|Alpha');

    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Alpha|Bravo');
});

it('writes nothing while it previews', function () {
    // ⚠️ The invariant the preview must not break: the hold is client state until the
    // put-down, or a five-place move files five audit rows.
    $before = Stored::order($this->parent->id);

    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');
    heldPreviewSend($page, 'ArrowUp');

    expect(Stored::order($this->parent->id))->toBe($before);
});

it('carries the row\'s own subtree with it', function () {
    // ⚠️ Since PA-10 a row's children group is its SIBLING, not its child, so moving
    // a row means moving its block — the row, its leaf slot and its group. Moving the
    // row alone would tear a subtree away from its parent on screen.
    $child = Category::create(['name' => 'Child', 'parent_id' => $this->alpha->id, 'position' => 0]);

    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    // The group still follows the row it belongs to.
    expect($page->script(
        "(() => { const row = document.querySelector('[data-ltree-key=\"{$this->alpha->id}\"]');"
        .' let n = row.nextElementSibling;'
        .' while (n && !n.hasAttribute("data-ltree-key")) {'
        ."   if (n.getAttribute('data-ltree-children-of') === '{$this->alpha->id}') return true;"
        .'   n = n.nextElementSibling; } return false; })()'
    ))->toBeTrue();

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$child->id}\"]') !== null"
    ))->toBeTrue();
});

it('puts the row back where it started when the hold is cancelled', function () {
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Alpha|Bravo');

    heldPreviewSend($page, 'Escape');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Bravo|Alpha');
    expect(heldPreviewAnnouncement($page))->toContain('Cancelled');
});

it('leaves the rendered order matching the stored order after a put-down', function () {
    // ⚠️ The preview is a lie until the server agrees with it. If the two ever
    // disagreed after a commit, the actor would be looking at an order that is not
    // the one their next move resolves against.
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');
    heldPreviewSend($page, 'Enter');

    $page->script('(async () => { await new Promise(r => setTimeout(r, 900)); return true; })()');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Alpha|Bravo');

    $stored = Category::query()
        ->where('parent_id', $this->parent->id)
        ->orderBy('position')
        ->pluck('name')
        ->all();

    expect($stored)->toBe(['Charlie', 'Alpha', 'Bravo']);
});

// ── The preview vs a server that says no ─────────────────────────────────────

it('puts the row back when the server refuses the put-down', function () {
    // ⚠️ Raised by the consumer's critique as the one untested path PA-15 opened. The
    // preview is optimistic, so between the put-down and the server's answer the actor
    // is looking at an order that may never be written — and a REFUSED move is exactly
    // the case where the two must reconcile. If the tree kept showing the preview, the
    // actor's next move would be resolved against an order that does not exist.
    //
    // ⚠️ Permission is flipped AFTER the hold begins, on purpose: that is a real
    // sequence (an actor's scope can change between queueing a move and committing it)
    // and it is the only way to reach a server refusal with a preview already on screen.
    // A pick-up refusal would never get this far — PA-13 stops it before the hold.
    $before = Stored::order($this->parent->id);

    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Alpha|Bravo');

    CategoryTreePage::$moveAuthorization = 'deny';

    heldPreviewSend($page, 'Enter');
    $page->script('(async () => { await new Promise(r => setTimeout(r, 900)); return true; })()');

    // Nothing was written…
    expect(Stored::order($this->parent->id))->toBe($before);

    // …and the screen agrees with the database again.
    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Bravo|Alpha');
});

it('says why the refused put-down changed nothing', function () {
    // ⚠️ Silence after a move that visibly happened and then un-happened is the worst
    // of both: the actor sees the row snap back with no reason given.
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    CategoryTreePage::$moveAuthorization = 'deny';

    heldPreviewSend($page, 'Enter');
    $page->script('(async () => { await new Promise(r => setTimeout(r, 900)); return true; })()');

    expect(heldPreviewAnnouncement($page))->toContain('cannot move');
});

// ── PA-16 — Tab leaves, and leaving abandons ─────────────────────────────────

it('abandons the hold when Tab moves focus out of the row', function () {
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');

    heldPreviewSend($page, 'Tab');

    expect(heldPreviewAnnouncement($page))->toContain('abandoned');
    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$this->alpha->id}\"]')?.getAttribute('aria-grabbed')"
    ))->toBeNull();
});

it('puts the row back when Tab abandons the hold', function () {
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);
    heldPreviewSend($page, ' ');
    heldPreviewSend($page, 'ArrowUp');
    heldPreviewSend($page, 'Tab');

    expect(heldPreviewRenderedOrder($page, $this->parent->id))->toBe('Charlie|Bravo|Alpha');
    expect(Stored::order($this->parent->id))->toBe(
        Category::query()->where('parent_id', $this->parent->id)->orderBy('position')->pluck('id')->all()
    );
});

it('does not swallow Tab when nothing is held', function () {
    // ⚠️ Tab must keep working as Tab. Consuming it while no hold exists would trap a
    // keyboard user inside the tree, which is a worse defect than the one PA-16 fixes.
    $page = visit('/admin/category-tree');
    heldPreviewFocus($page, $this->alpha->id);

    heldPreviewSend($page, 'Tab');

    expect(heldPreviewAnnouncement($page))->not->toContain('abandoned');
});

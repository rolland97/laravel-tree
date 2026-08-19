<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;

/**
 * PA-18 in a REAL browser — what the keyboard does, and what it says.
 *
 * ⚠️ The bridge cases prove the MARKUP the server sent. They cannot prove the
 * CONTROLLER agrees, and for PA-18 the controller is most of the contract: C3 is about
 * a binding not being registered and C4 about a live region staying empty, neither of
 * which exists in served html (AGENTS.md R-028).
 *
 * ⚠️ **The failure this file is really guarding against.** An announcement of
 * "you cannot move this" under C4 is NOT a pass. It means `canMoveNode()` answered a
 * permission question the page never asked — the exact defect the consumer's research
 * R1 describes, reproduced rather than fixed. `UnorderedTreePage` therefore leaves
 * `canMoveNode()` at its default `true`, so any refusal wording here can only have come
 * from the wrong slot.
 *
 * ⚠️ Every "nothing happened" case waits before it looks. An assertion that something
 * did NOT happen is only as strong as the time it waits (validation-log finding F8) —
 * without the grace period below, the guard can outrun the very announcement it forbids
 * and pass against a live defect.
 */
beforeEach(function () {
    // ⚠️ Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

/**
 * Wait until the controller has actually booted.
 *
 * `init()` adopts the first row, so a roving tabindex of 0 is the observable proof
 * that Alpine ran. Without it every case here is a coin toss on load order — and the
 * ones asserting that nothing happened would pass against a page whose controller had
 * not started yet, which is the strongest way to be wrong.
 */
function reorderOffBootedTree(object $page): object
{
    $page->assertPresent('[data-ltree-key]');
    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        .' if (document.querySelector(\'[data-ltree-key][tabindex="0"]\')) return true;'
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );

    return $page;
}

/**
 * Press a key on a row and report whether the controller CONSUMED the keystroke.
 *
 * ⚠️ `dispatchEvent()` returns false when a listener called `preventDefault()`, which
 * is the sharpest available reading of C3's "the binding is not registered". A guard
 * that only checked for an absent announcement would also pass against a binding that
 * ran, swallowed the key and happened to say nothing.
 */
function reorderOffPressKey(object $page, int|string $key, string $keyName): mixed
{
    $page->assertPresent('[data-ltree-key="'.$key.'"]');
    $page->script("document.querySelector('[data-ltree-key=\"{$key}\"]').focus()");

    $consumed = $page->script(
        '(() => { const fired = document.activeElement.dispatchEvent('
        ."new KeyboardEvent('keydown', { key: '{$keyName}', bubbles: true, cancelable: true }));"
        .' return fired === false; })()'
    );

    // ⚠️ The grace period finding F8 asks for, AFTER the press and BEFORE any
    // "nothing happened" assertion reads the page.
    $page->script('(async () => { await new Promise(r => setTimeout(r, 300)); return true; })()');

    return $consumed;
}

function reorderOffAnnouncement(object $page): mixed
{
    return $page->script("(document.querySelector('.ltree-live-region')?.textContent ?? 'NO-REGION').trim()");
}

function reorderOffIsHeld(object $page, int|string $key): mixed
{
    return $page->script(
        "document.querySelector('[data-ltree-key=\"{$key}\"]')?.getAttribute('aria-grabbed')"
    );
}

function reorderOffFocusedKey(object $page): mixed
{
    return $page->script('document.activeElement?.dataset?.ltreeKey ?? null');
}

function reorderOffExpanded(object $page, int|string $key): mixed
{
    return $page->script(
        "document.querySelector('[data-ltree-key=\"{$key}\"]')?.getAttribute('aria-expanded')"
    );
}

function reorderOffWaitForBranchDisplay(object $page, int|string $key, bool $hidden): void
{
    $wanted = $hidden ? '===' : '!==';

    $page->script(
        '(async () => { for (let i = 0; i < 80; i++) {'
        ." const c = document.querySelector('[data-ltree-children-of=\"{$key}\"]');"
        ." if (c && getComputedStyle(c).display {$wanted} 'none') return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );
}

// ── C3 — the move bindings are not registered ────────────────────────────────

it('does not pick a node up when Space is pressed', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->charlie->id, ' ');

    expect(reorderOffIsHeld($page, $this->charlie->id))->toBeNull();
    expect($page->script("document.querySelectorAll('.ltree-held').length"))->toBe(0);
});

it('does not even consume the Space keystroke', function () {
    // ⚠️ The binding is ABSENT, not merely silent. A handler that ran, held nothing
    // and swallowed the key would pass the case above while still taking a keystroke
    // away from the browser on a page that has no use for it.
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    expect(reorderOffPressKey($page, $this->charlie->id, ' '))->toBeFalse();
});

it('consumes Space on the ordered twin, which is the discrimination', function () {
    // ⚠️ Without this, every case in this file would pass against a package whose
    // keyboard had simply stopped working — for every host, ordering or not.
    $page = reorderOffBootedTree(visit('/admin/ordered-twin-tree'));

    expect(reorderOffPressKey($page, $this->charlie->id, ' '))->toBeTrue();
    expect(reorderOffIsHeld($page, $this->charlie->id))->toBe('true');
});

// ── C4 — nothing is announced about ordering ─────────────────────────────────

it('announces nothing at all when Space is pressed', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->charlie->id, ' ');

    expect(reorderOffAnnouncement($page))->toBe('');
});

it('does not tell the actor they lack permission to move', function () {
    // ⚠️ THE case this file exists for. `canMoveNode()` is left at its default `true`
    // on this host, so a refusal here could only mean the permission slot answered a
    // question the page never asked — the R1 defect reproduced, not a pass.
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->charlie->id, ' ');

    expect(reorderOffAnnouncement($page))
        ->not->toContain('cannot move')
        ->not->toContain('only child')
        ->not->toContain('Picked up');
});

it('keeps the live region empty through the arrow keys too', function () {
    // Arrows still TRAVERSE — that is R-012 and C6 — they must simply never announce
    // a position, because there is no move in progress and no order to report.
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->delta->id, 'ArrowDown');
    reorderOffPressKey($page, $this->charlie->id, 'ArrowUp');

    expect(reorderOffAnnouncement($page))->toBe('');
});

it('still has a live region for a host to report into', function () {
    // ⚠️ EMPTY, not absent. PA-18 retires ordering, not the announcement channel: a
    // server-raised report still reaches a screen-reader user through this node.
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    expect(reorderOffAnnouncement($page))->not->toBe('NO-REGION');
});

// ── C6 — traversal, expand and collapse are untouched ────────────────────────

it('still walks the arrow keys down the displayed rows', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->delta->id, 'ArrowDown');

    expect(reorderOffFocusedKey($page))->toBe((string) $this->charlie->id);
});

it('still collapses with ArrowLeft and expands with ArrowRight', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    reorderOffPressKey($page, $this->delta->id, 'ArrowLeft');
    reorderOffWaitForBranchDisplay($page, $this->delta->id, hidden: true);
    expect(reorderOffExpanded($page, $this->delta->id))->toBe('false');

    reorderOffPressKey($page, $this->delta->id, 'ArrowRight');
    reorderOffWaitForBranchDisplay($page, $this->delta->id, hidden: false);
    expect(reorderOffExpanded($page, $this->delta->id))->toBe('true');
});

it('still collapses when the chevron is clicked', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    reorderOffWaitForBranchDisplay($page, $this->delta->id, hidden: true);

    expect(reorderOffExpanded($page, $this->delta->id))->toBe('false');
});

// ── C7 — search-reveal (PA-11) is untouched ──────────────────────────────────

it('still reveals a match inside a branch nobody opened', function () {
    $page = reorderOffBootedTree(visit('/admin/unordered-tree'));

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    reorderOffWaitForBranchDisplay($page, $this->delta->id, hidden: true);

    $page->type('input[type="search"]', 'Charlie');
    $page->script('(async () => { await new Promise(r => setTimeout(r, 1200)); return true; })()');
    reorderOffWaitForBranchDisplay($page, $this->delta->id, hidden: false);

    expect($page->script(
        "(() => { const row = document.querySelector('[data-ltree-key=\"{$this->charlie->id}\"]');"
        .' return row === null ? null : row.offsetParent !== null; })()'
    ))->toBeTrue();
});

// ── C5, live ─────────────────────────────────────────────────────────────────

it('reports no accessibility violations with ordering retired', function () {
    reorderOffBootedTree(visit('/admin/unordered-tree'))->assertNoAccessibilityIssues();
});

// ── PA-19 × PA-18 — both amendments must hold TOGETHER ───────────────────────

/*
| ⚠️ These exist because a bridge guard could not catch the failure.
|
| PA-19 moves the element carrying `x-data="ltree(strings, collapsed, reorder)"` into
| `tree-content.blade.php`. Dropping the THIRD argument during that move leaves markup
| that is entirely correct — no handle, nothing draggable, every bridge assertion green
| — while the controller defaults `reorderEnabled` back to `true` and the keyboard picks
| rows up on a page that has no ordering. Measured: with the argument dropped, the whole
| bridge suite stayed green at 26 passed.
|
| That half-state — no affordance, but the keyboard still holding and announcing — is
| the precise thing PA-18 exists to prevent, so it needs a guard where the controller
| actually runs.
*/
it('does not pick up on a composed host that retired ordering', function () {
    $page = reorderOffBootedTree(visit('/admin/composed-unordered-tree'));

    expect(reorderOffPressKey($page, $this->charlie->id, ' '))->toBeFalse();
    expect(reorderOffIsHeld($page, $this->charlie->id))->toBeNull();
    expect(reorderOffAnnouncement($page))->toBe('');
});

it('still picks up on a composed host that kept ordering', function () {
    // ⚠️ The discrimination. Without it, a composed layout whose controller never
    // booted at all would pass the case above.
    $page = reorderOffBootedTree(visit('/admin/composed-tree'));

    expect(reorderOffPressKey($page, $this->charlie->id, ' '))->toBeTrue();
    expect(reorderOffIsHeld($page, $this->charlie->id))->toBe('true');
});

it('renders the tree inside the composed host layout in a real browser', function () {
    // ⚠️ The bridge proves the server sent it. This proves Alpine booted INSIDE the
    // host's own regions — a layout that renders but whose controller never
    // initialises looks identical in served html.
    $page = reorderOffBootedTree(visit('/admin/composed-tree'));

    $page->assertPresent('[data-host-region="breadcrumb"]')
        ->assertPresent('[data-host-region="pane"]')
        ->assertPresent('[data-host-region="sidebar"] [role="tree"]');

    expect($page->script("document.querySelectorAll('[data-ltree-key][tabindex=\"0\"]').length"))->toBe(1);
});

it('reports no accessibility violations in the composed layout', function () {
    reorderOffBootedTree(visit('/admin/composed-tree'))->assertNoAccessibilityIssues();
});

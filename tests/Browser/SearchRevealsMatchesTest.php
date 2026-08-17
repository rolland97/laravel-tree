<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;

/**
 * PA-11 — a search shows what it matched, whatever the branch state was.
 *
 * ⚠️ The two halves of the search were built by different sides and never met. The
 * SERVER narrows the rows and deliberately keeps a match's ANCESTORS so it stays
 * reachable; the CLIENT decides which branches are open and knows nothing about the
 * search. With PA-8's collapsed default that composes into a tree where every match
 * below the root is present in the DOM and invisible — a search that finds things and
 * shows you none of them.
 *
 * ⚠️ It is not only PA-8's problem. Any actor who had closed a branch before typing
 * got the same nothing, on any host, since the search shipped — collapsed-by-default
 * just made it the first experience rather than an occasional one. Found by the first
 * consumer, whose own tree auto-revealed matches and whose test says so in a comment
 * ("a search auto-reveals matches, so the branch needs no expanding").
 */
beforeEach(function () {
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->echo = Category::create(['name' => 'Echo', 'parent_id' => $this->charlie->id, 'position' => 0]);
});

function searchRevealWaitForRow(object $page, int|string $key, bool $visible): void
{
    $wanted = $visible ? '!==' : '===';

    $page->script(
        '(async () => { for (let i = 0; i < 120; i++) {'
        ." const row = document.querySelector('[data-ltree-key=\"{$key}\"]');"
        ." if (row && getComputedStyle(row).display {$wanted} 'none'"
        ."     && (row.offsetParent !== null) {$wanted} false) return true;"
        .' await new Promise(r => setTimeout(r, 25)); } return false; })()'
    );
}

function searchRevealRowIsVisible(object $page, int|string $key): mixed
{
    // `offsetParent` is null for anything inside a `display: none` ancestor, which is
    // exactly how a collapsed branch hides its rows — the row's own display is
    // untouched, so asking about the row alone would say "visible" and be wrong.
    return $page->script(
        "(() => { const row = document.querySelector('[data-ltree-key=\"{$key}\"]');"
        .' return row === null ? null : row.offsetParent !== null; })()'
    );
}

function searchRevealType(object $page, string $term): void
{
    $page->type('input[type="search"]', $term);

    // wire:model.live.debounce.300ms, then the round trip.
    $page->script('(async () => { await new Promise(r => setTimeout(r, 1200)); return true; })()');
}

it('reveals a match that sits inside a closed branch', function () {
    $page = visit('/admin/collapsed-tree');
    searchRevealWaitForRow($page, $this->charlie->id, visible: false);

    expect(searchRevealRowIsVisible($page, $this->charlie->id))->toBeFalse();

    searchRevealType($page, 'Charlie');

    expect(searchRevealRowIsVisible($page, $this->charlie->id))->toBeTrue();
});

it('reveals a match two levels down', function () {
    // ⚠️ Deeper than one branch on purpose: revealing only the first level would
    // pass the case above while still hiding most of a real tree's matches.
    $page = visit('/admin/collapsed-tree');

    searchRevealType($page, 'Echo');

    expect(searchRevealRowIsVisible($page, $this->echo->id))->toBeTrue();
});

it('goes back to the branch state the actor had when the search is cleared', function () {
    // ⚠️ Revealing is not the same as EXPANDING. The search must not rewrite what the
    // actor had open, or clearing the box leaves a tree they never opened — and on a
    // large tree that is every branch at once.
    $page = visit('/admin/collapsed-tree');

    searchRevealType($page, 'Charlie');
    expect(searchRevealRowIsVisible($page, $this->charlie->id))->toBeTrue();

    searchRevealType($page, '');

    searchRevealWaitForRow($page, $this->charlie->id, visible: false);
    expect(searchRevealRowIsVisible($page, $this->charlie->id))->toBeFalse();
});

it('leaves a branch the actor opened during a search open afterwards', function () {
    // The other direction of the same rule: an explicit choice outranks the reveal.
    $page = visit('/admin/collapsed-tree');

    $page->click('[data-ltree-key="'.$this->delta->id.'"] [data-ltree-chevron]');
    searchRevealWaitForRow($page, $this->charlie->id, visible: true);

    searchRevealType($page, 'Charlie');
    searchRevealType($page, '');

    expect(searchRevealRowIsVisible($page, $this->charlie->id))->toBeTrue();
});

it('reports no accessibility violations while a search is revealing matches', function () {
    $page = visit('/admin/collapsed-tree');

    searchRevealType($page, 'Charlie');

    $page->assertNoAccessibilityIssues();
});

it('lets the arrow keys reach a revealed match', function () {
    // ⚠️ The reveal has to change what `displayedRows()` walks, not just what is
    // painted. A row a screen-reader user can see and cannot reach is half a fix.
    $page = visit('/admin/collapsed-tree');

    searchRevealType($page, 'Charlie');

    $page->script('document.querySelector(\'[data-ltree-key][tabindex="0"]\')?.focus()');
    $page->script(
        '(() => { document.activeElement.dispatchEvent('
        ."new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true })); })()"
    );
    $page->script('(async () => { await new Promise(r => setTimeout(r, 300)); return true; })()');

    expect($page->script('document.activeElement?.dataset?.ltreeKey ?? null'))
        ->toBe((string) $this->charlie->id);
});

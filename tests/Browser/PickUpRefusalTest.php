<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * PA-12 and PA-13 — what the keyboard refuses to pick up at all.
 *
 * ⚠️ Both refusals are a COURTESY. The real guards are server-side and unchanged:
 * `commit()` reports an only-child reorder and writes nothing, and `authorizeTreeMove()`
 * re-decides permission on every committing path. What is wrong is that the keyboard
 * let an actor start a move it could never finish, and told them so only afterwards.
 *
 * PA-12: an only child could be picked up and announced as "picked up, 1 of 1", and
 * heard `only_child` only when an arrow key was pressed. The first consumer's tree
 * refused the pick-up and said why — the announcement exists precisely so the actor
 * does not begin.
 *
 * PA-13: the only early refusal the package had was driven by `data-ltree-locked`,
 * which comes from `isValidTreeTarget()` — "may this node RECEIVE children". That is a
 * different question from "may this actor MOVE this node", and using one to answer the
 * other meant a view-only actor picked up a row freely and was refused at the far end
 * of a round trip, while a node nobody may nest into was refused for the wrong reason.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;
});

function pickUpRefusalAnnouncement(object $page): mixed
{
    return $page->script("(document.querySelector('.ltree-live-region')?.textContent ?? 'NO-REGION').trim()");
}

function pickUpRefusalPressSpace(object $page, int|string $key): void
{
    $page->assertPresent('[data-ltree-key="'.$key.'"]');
    $page->script("document.querySelector('[data-ltree-key=\"{$key}\"]').focus()");
    $page->script(
        '(() => { document.activeElement.dispatchEvent('
        ."new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true })); })()"
    );
    $page->script('(async () => { await new Promise(r => setTimeout(r, 300)); return true; })()');
}

function pickUpRefusalIsHeld(object $page, int|string $key): mixed
{
    return $page->script(
        "document.querySelector('[data-ltree-key=\"{$key}\"]')?.getAttribute('aria-grabbed')"
    );
}

// ── PA-12 — an only child is refused, not picked up ──────────────────────────

it('refuses to pick up an only child and says why', function () {
    $solo = Category::create(['name' => 'Solo', 'position' => 0]);
    $only = Category::create(['name' => 'Only', 'parent_id' => $solo->id, 'position' => 0]);

    $page = visit('/admin/category-tree');
    pickUpRefusalPressSpace($page, $only->id);

    expect(pickUpRefusalAnnouncement($page))->toContain('only child');
    expect(pickUpRefusalIsHeld($page, $only->id))->toBeNull();
});

it('still picks up a node that has siblings', function () {
    // ⚠️ The discrimination that matters: a refusal broad enough to catch a real
    // group would be worse than the behaviour it replaces.
    $parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $first = Category::create(['name' => 'First', 'parent_id' => $parent->id, 'position' => 0]);
    Category::create(['name' => 'Second', 'parent_id' => $parent->id, 'position' => 1]);

    $page = visit('/admin/category-tree');
    pickUpRefusalPressSpace($page, $first->id);

    expect(pickUpRefusalAnnouncement($page))->toContain('Picked up');
    expect(pickUpRefusalIsHeld($page, $first->id))->toBe('true');
});

it('counts only the siblings the actor can SEE when it decides', function () {
    // ⚠️ Privacy first: a node whose only visible sibling is hidden from this actor IS
    // an only child as far as they are concerned, and telling them otherwise —
    // by picking up and then failing — discloses that the hidden row exists.
    $parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $seen = Category::create(['name' => 'Seen', 'parent_id' => $parent->id, 'position' => 0]);
    Category::create(['name' => 'Unseen', 'parent_id' => $parent->id, 'position' => 1, 'is_visible' => false]);

    $page = visit('/admin/category-tree');
    pickUpRefusalPressSpace($page, $seen->id);

    expect(pickUpRefusalAnnouncement($page))->toContain('only child');
    expect(pickUpRefusalIsHeld($page, $seen->id))->toBeNull();
});

// ── PA-13 — the host says which nodes this actor may move ────────────────────

it('refuses to pick up a node the host says this actor may not move', function () {
    CategoryTreePage::$immovableName = 'Bravo';

    $parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $bravo = Category::create(['name' => 'Bravo', 'parent_id' => $parent->id, 'position' => 0]);
    Category::create(['name' => 'Alpha', 'parent_id' => $parent->id, 'position' => 1]);

    $page = visit('/admin/category-tree');
    pickUpRefusalPressSpace($page, $bravo->id);

    expect(pickUpRefusalAnnouncement($page))->toContain('cannot move');
    expect(pickUpRefusalIsHeld($page, $bravo->id))->toBeNull();
});

it('still picks up its siblings, which the host did permit', function () {
    // Per NODE, not per tree. A slot that refused the whole page would pass the case
    // above and be useless.
    CategoryTreePage::$immovableName = 'Bravo';

    $parent = Category::create(['name' => 'Parent', 'position' => 0]);
    Category::create(['name' => 'Bravo', 'parent_id' => $parent->id, 'position' => 0]);
    $alpha = Category::create(['name' => 'Alpha', 'parent_id' => $parent->id, 'position' => 1]);

    $page = visit('/admin/category-tree');
    pickUpRefusalPressSpace($page, $alpha->id);

    expect(pickUpRefusalIsHeld($page, $alpha->id))->toBe('true');
});

it('does not refuse a pick-up merely because a node cannot receive children', function () {
    // ⚠️ The conflation PA-13 exists to end. `is_active` answers "may this receive
    // children"; an inactive node is still the actor's to reorder, and the package
    // used that flag to refuse moving it at all.
    $parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $inactive = Category::create(['name' => 'Inactive', 'parent_id' => $parent->id, 'position' => 0, 'is_active' => false]);
    Category::create(['name' => 'Active', 'parent_id' => $parent->id, 'position' => 1]);

    $page = visit('/admin/category-tree');

    expect($page->script(
        "document.querySelector('[data-ltree-key=\"{$inactive->id}\"]')?.getAttribute('data-ltree-locked')"
    ))->toBe('true');

    pickUpRefusalPressSpace($page, $inactive->id);

    expect(pickUpRefusalIsHeld($page, $inactive->id))->toBe('true');
});

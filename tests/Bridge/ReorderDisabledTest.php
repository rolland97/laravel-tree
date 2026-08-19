<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\OrderedTwinTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\UnorderedTreePage;

/**
 * PA-18 — a page-level slot that switches sibling ordering OFF without losing the tree.
 *
 * ⚠️ The package could already refuse a move per NODE and per ACTOR (`canMoveNode()`,
 * `authorizeTreeMove()`). It could not say "this page has no concept of order". A page
 * answering `canMoveNode()` false everywhere still rendered a drag handle on every row
 * and still answered Space with a permission refusal — telling a screen-reader user
 * they lack permission for a concept the page has retired (the consumer's research R1).
 *
 * ⚠️ This is an AMENDMENT, not a rewrite, and the C5–C8 cases below are the whole
 * point. PA-18 removes an AFFORDANCE. The roles, every `aria-*`, expand/collapse and
 * the search must come through untouched, or the amendment is wrong.
 *
 * ⚠️ The default is TRUE. Every existing host already has ordering, and a false
 * default would silently remove it from all of them — which is why the regression
 * cases here assert the default path as loudly as the new one.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;

    // ⚠️ Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

afterEach(function () {
    CategoryTreePage::$immovableName = null;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$hideBravo = false;
});

/**
 * Every `aria-*` attribute NAME the page rendered, sorted and de-duplicated.
 *
 * ⚠️ Names, not values: the values carry ids and counts that differ between two
 * separately-rendered pages for reasons that have nothing to do with this slot. What
 * C5 claims is that PA-18 does not REMOVE or ADD an ARIA property, and that is exactly
 * what a name set can settle.
 *
 * @return list<string>
 */
function ariaNamesIn(string $html): array
{
    preg_match_all('/\b(aria-[a-z]+)=/', $html, $matches);

    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

/**
 * Every `role` VALUE the page rendered, sorted and de-duplicated.
 *
 * @return list<string>
 */
function roleValuesIn(string $html): array
{
    preg_match_all('/\brole="([a-z]+)"/', $html, $matches);

    $roles = array_values(array_unique($matches[1]));
    sort($roles);

    return $roles;
}

// ── The default, which no existing host may lose ─────────────────────────────

it('offers ordering to a host that says nothing', function () {
    // ⚠️ REGRESSION guard, and the most important case in this file. A default of
    // false would silently retire ordering on every host that already has it.
    expect(Livewire::test(CategoryTreePage::class)->instance()->treeReorderEnabled())->toBeTrue();
    expect(Livewire::test(OrderedTwinTreePage::class)->instance()->treeReorderEnabled())->toBeTrue();
});

it('still renders a drag handle for a host that says nothing', function () {
    expect(Livewire::test(OrderedTwinTreePage::class)->html())
        ->toContain('ltree-handle')
        ->toContain('data-ltree-handle')
        ->toContain('draggable="true"');
});

// ── C1 / C2 — the affordance is gone ─────────────────────────────────────────

it('renders no drag handle on any row when the host has retired ordering', function () {
    expect(Livewire::test(UnorderedTreePage::class)->html())
        ->not->toContain('ltree-handle')
        ->not->toContain('data-ltree-handle');
});

it('renders nothing draggable when the host has retired ordering', function () {
    // ⚠️ Asserted separately from the handle even though one blade conditional
    // removes both today. `draggable="true"` is what the BROWSER acts on, and a
    // later change that kept the attribute while dropping the class would leave a
    // page that still starts drags and looks like it cannot.
    expect(Livewire::test(UnorderedTreePage::class)->html())
        ->not->toContain('draggable="true"');
});

// ── C5 — every role and every aria-* survives ────────────────────────────────

it('keeps every ARIA property the ordered twin renders', function () {
    // ⚠️ The two pages are the SAME page apart from the one slot — `UnorderedTreePage`
    // extends `OrderedTwinTreePage` and overrides nothing else — so a difference here
    // can only have come from PA-18.
    $ordered = Livewire::test(OrderedTwinTreePage::class)->html();
    $unordered = Livewire::test(UnorderedTreePage::class)->html();

    expect(ariaNamesIn($unordered))->toBe(ariaNamesIn($ordered));
});

it('keeps every role the ordered twin renders', function () {
    $ordered = Livewire::test(OrderedTwinTreePage::class)->html();
    $unordered = Livewire::test(UnorderedTreePage::class)->html();

    expect(roleValuesIn($unordered))->toBe(roleValuesIn($ordered));
    expect(roleValuesIn($unordered))->toContain('tree', 'treeitem', 'group');
});

it('still names each row by its own name alone', function () {
    // ⚠️ R-014's property, restated on the new path. A treeitem computes its name from
    // its CONTENTS, and removing an element from inside a row is exactly the kind of
    // change that can move what `aria-labelledby` points at.
    expect(Livewire::test(UnorderedTreePage::class)->html())
        ->toContain('aria-labelledby="ltree-name-'.$this->delta->id.'"')
        ->toContain('id="ltree-name-'.$this->delta->id.'"');
});

it('still counts the rendered siblings on every row', function () {
    // ⚠️ A privacy requirement before it is a convention (AGENTS.md R-015), so it is
    // asserted on the new path rather than assumed to have come along.
    expect(Livewire::test(UnorderedTreePage::class)->html())
        ->toContain('aria-posinset="1"')
        ->toContain('aria-setsize="2"')
        ->toContain('aria-level="1"');
});

// ── C6 — the branch machinery is untouched ───────────────────────────────────

it('still renders the chevron and the expanded state', function () {
    expect(Livewire::test(UnorderedTreePage::class)->html())
        ->toContain('data-ltree-chevron')
        ->toContain('aria-expanded="true"')
        ->toContain('data-ltree-children-of="'.$this->delta->id.'"');
});

it('still keeps a collapsed branch\'s children in the DOM', function () {
    expect(Livewire::test(UnorderedTreePage::class)->html())->toContain('Charlie');
});

// ── C7 — the search is untouched ─────────────────────────────────────────────

it('still renders the search input and reveals what it matched', function () {
    $page = Livewire::test(UnorderedTreePage::class);

    expect($page->html())->toContain('type="search"');

    // PA-11: a match keeps its ANCESTORS so it stays reachable.
    $page->set('treeSearch', 'Charlie');

    expect($page->html())
        ->toContain('Charlie')
        ->toContain('Delta')
        ->not->toContain('Alpha');
});

// ── C8 — PA-13's two attributes keep their meanings on the default path ──────

it('keeps data-ltree-immovable meaning "this actor may not move this node"', function () {
    CategoryTreePage::$immovableName = 'Bravo';

    $html = Livewire::test(CategoryTreePage::class)->html();

    expect($html)->toContain('data-ltree-immovable="true"');

    // ⚠️ And it is still PER NODE. A page-level answer arriving through the wrong
    // slot would mark every row, which is the conflation PA-18 exists to prevent.
    expect(substr_count($html, 'data-ltree-immovable="true"'))->toBe(1);
});

it('keeps data-ltree-locked meaning "this node may not receive children"', function () {
    $frozen = Category::create(['name' => 'Frozen', 'position' => 2, 'is_active' => false]);

    $html = Livewire::test(CategoryTreePage::class)->html();

    expect($html)->toContain('data-ltree-locked="true"');
    expect(substr_count($html, 'data-ltree-locked="true"'))->toBe(1);
    expect($html)->toContain((string) $frozen->id);
});

it('still renders the handle for a node this actor may not move', function () {
    // ⚠️ The distinction in one case. PA-13 refuses a pick-up on a node; it does not
    // retire ordering, so the affordance stays and the refusal is announced. Only
    // PA-18 removes the handle.
    CategoryTreePage::$immovableName = 'Bravo';

    expect(Livewire::test(CategoryTreePage::class)->html())
        ->toContain('data-ltree-immovable="true"')
        ->toContain('data-ltree-handle');
});

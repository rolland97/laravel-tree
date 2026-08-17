<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * C1 — the orphan edge case.
 *
 * spec.md § Edge Cases: *"The host's visible query returns a node whose parent it
 * does not return — an orphan from the actor's perspective. It must render at the
 * root of what the actor can see without implying its true parent."*
 *
 * ⚠️ This shipped broken. `nodesByParent()` filed every node under its raw parent
 * key and the blade walked only from the root group, so a node the actor WAS
 * permitted to see was filed under an unreachable key and never rendered at all —
 * invisible, and therefore unreorderable. Found by the T097 critique, not by the
 * suite, because nothing tested it.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    // The actor may not see this parent...
    $this->hiddenParent = Category::create([
        'name' => 'HiddenParent',
        'position' => 0,
        'is_visible' => false,
    ]);

    // ...but may see these two children of it.
    $this->orphanZulu = Category::create([
        'name' => 'Zulu',
        'parent_id' => $this->hiddenParent->id,
        'position' => 0,
    ]);
    $this->orphanAlpha = Category::create([
        'name' => 'Alpha',
        'parent_id' => $this->hiddenParent->id,
        'position' => 1,
    ]);

    $this->realRoot = Category::create(['name' => 'RealRoot', 'position' => 1]);
});

it('renders a node whose parent the actor cannot see', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('Zulu');
});

it('renders every such node, not merely the first', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('Alpha');
});

it('still does not disclose the parent the actor may not see', function () {
    // ⚠️ "without implying its true parent". Rendering the orphan must not leak
    // the existence of the row above it.
    Livewire::test(CategoryTreePage::class)->assertDontSee('HiddenParent');
});

it('displays an orphan at the root of what the actor can see', function () {
    $page = Livewire::test(CategoryTreePage::class)->instance();

    $names = array_map(fn ($n) => $n->name, $page->treeDisplayRoots());

    // Real roots first, then orphan groups. Read order INSIDE the hidden group is
    // Zulu(0), Alpha(1) — position first — and the names deliberately contradict
    // alphabetical order, so an implementation that sorted by name instead of
    // position could not produce this list (AGENTS.md R-025).
    expect($names)->toBe(['RealRoot', 'Zulu', 'Alpha']);
});

it('counts an orphan among its OWN rendered siblings, not among the roots', function () {
    // ⚠️ The set size still describes the rendered members of the node's real
    // group. Reporting "1 of 3" here would imply the orphan is a sibling of
    // RealRoot, which is a different disclosure: it would tell the actor the
    // orphan has no parent, when in fact it has one they cannot see.
    $page = Livewire::test(CategoryTreePage::class)->instance();

    expect($page->treeSetSizeFor($this->orphanZulu))->toBe(2);
    expect($page->treePositionFor($this->orphanZulu))->toBe(1);
    expect($page->treePositionFor($this->orphanAlpha))->toBe(2);
});

it('counts a real root among the real roots', function () {
    $page = Livewire::test(CategoryTreePage::class)->instance();

    expect($page->treeSetSizeFor($this->realRoot))->toBe(1);
    expect($page->treePositionFor($this->realRoot))->toBe(1);
});

it('keeps an orphan a member of its real group for placement', function () {
    // The DISPLAY promotes it to the root; the placement rule must not. It is
    // still a child of the hidden parent, and its ordering is still resolved
    // inside that group.
    $page = Livewire::test(CategoryTreePage::class)->instance();

    expect($page->treeParentKeyFor($this->orphanZulu))->toBe((string) $this->hiddenParent->id);
    expect($page->treeParentKeyFor($this->realRoot))->toBe('');
});

it('states that nothing matches rather than rendering an empty broken tree', function () {
    // spec.md § Edge Cases: "A search is active and matches nothing. The tree must
    // state that rather than appear empty and broken." Untested until the critique.
    //
    // ⚠️ This asserted the generic 'Nothing to show' until finding F24. It now
    // asserts the SEARCH wording, and that is the assertion finally saying what
    // the spec sentence above it always said: "state THAT" means state that
    // nothing MATCHED, not that the tree is empty — which, with a search active
    // and nodes present, was untrue. The requirement did not change; the string
    // stopped contradicting it.
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'no-such-node-anywhere')
        ->assertSee('Nothing matches that search.');
});

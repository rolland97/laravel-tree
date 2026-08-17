<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * C3 — the render must not issue a query per row.
 *
 * ⚠️ This guard exists because a fix for the orphan edge case (C1) turned a
 * one-query render into a 45-query one for 21 nodes, and the whole suite stayed
 * green: `treePositionFor()` and `treeSetSizeFor()` are called per row and each
 * re-ran `nodesByParent()`, which queries. plan.md scopes this tree at "hundreds
 * of nodes", so that is ~600 queries for one page.
 *
 * ⚠️ The assertion is that the count is CONSTANT in the number of nodes, not that
 * it is under some threshold. A threshold of "fewer than 100" would have passed
 * against both the one-query version and the forty-five-query one, and so would
 * have caught nothing. Comparing two tree sizes is what makes it discriminate.
 */
function queriesToRender(): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test(CategoryTreePage::class)->html();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}

function seedFlatTree(int $children): void
{
    Category::query()->delete();

    $root = Category::create(['name' => 'Root', 'position' => 0]);

    for ($i = 0; $i < $children; $i++) {
        Category::create(['name' => 'Child'.$i, 'parent_id' => $root->id, 'position' => $i]);
    }
}

beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
});

it('renders in a number of queries that does not grow with the tree', function () {
    seedFlatTree(4);
    $small = queriesToRender();

    seedFlatTree(60);
    $large = queriesToRender();

    expect($large)->toBe(
        $small,
        "rendering 61 nodes took {$large} queries where 5 nodes took {$small} — the render scales with the tree"
    );
});

it('renders a deep tree in the same number of queries as a shallow one', function () {
    // Depth, not breadth: a recursive include that queried per LEVEL would pass the
    // breadth test above and fail this one.
    Category::query()->delete();
    $parentId = null;
    for ($i = 0; $i < 12; $i++) {
        $node = Category::create(['name' => 'Level'.$i, 'parent_id' => $parentId, 'position' => 0]);
        $parentId = $node->id;
    }
    $deep = queriesToRender();

    seedFlatTree(4);
    $shallow = queriesToRender();

    expect($deep)->toBe($shallow);
});

it('keeps the render query count in single figures', function () {
    // Constant is the property that matters, but a constant of two hundred would
    // still be wrong. This pins the order of magnitude.
    seedFlatTree(30);

    expect(queriesToRender())->toBeLessThan(10);
});

it('serves a fresh group after a write on the SAME instance', function () {
    // ⚠️ THE risk memoisation introduces. This is exercised directly on one
    // component instance — populate, write, re-read — rather than through
    // Livewire::test(), because Livewire builds a NEW instance per request and an
    // instance read after `->call()` never had the cache populated at all. A guard
    // written that way passed happily against a page that never forgot anything.
    //
    // Nor does asserting the returned HTML help: within a request the write
    // happens BEFORE the first render, so the cache is not yet warm. The ordering
    // that breaks a stale cache does not occur on today's code paths — it would
    // the moment anything before the write consulted nodesByParent(), which is a
    // change a future slice could easily make.
    $root = Category::create(['name' => 'Root', 'position' => 0]);
    $delta = Category::create(['name' => 'Delta', 'parent_id' => $root->id, 'position' => 0]);
    $charlie = Category::create(['name' => 'Charlie', 'parent_id' => $root->id, 'position' => 1]);

    $page = Livewire::test(CategoryTreePage::class)->instance();

    $before = array_map(fn ($n) => $n->name, $page->nodesByParent()[(string) $root->id] ?? []);
    expect($before)->toBe(['Delta', 'Charlie']);

    $page->placeNode($charlie->id, $root->id, $delta->id, 'Before', [$delta->id, $charlie->id]);

    expect(Stored::order($root->id))->toBe([$charlie->id, $delta->id]);

    $after = array_map(fn ($n) => $n->name, $page->nodesByParent()[(string) $root->id] ?? []);

    expect($after)->toBe(
        ['Charlie', 'Delta'],
        'the page served the order it held BEFORE its own write'
    );
});

it('reflects a change of search term rather than serving the previous results', function () {
    // The other way a memo goes stale: the search narrows what is displayed, and a
    // cache keyed on nothing would keep showing the unfiltered tree.
    seedFlatTree(3);

    $page = Livewire::test(CategoryTreePage::class);
    $page->html();

    $page->set('treeSearch', 'Child1');

    $ids = $page->instance()->searchVisibleIds();

    expect($ids)->not->toBeNull();
    expect($page->html())->not->toContain('Child2');
});

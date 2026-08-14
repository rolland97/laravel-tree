<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Page;

it('reports a root node as having no parent', function () {
    $root = Category::create(['name' => 'Alpha', 'position' => 0]);

    expect($root->treeParentId())->toBeNull();
});

it('reports the parent key of a child', function () {
    $root = Category::create(['name' => 'Alpha', 'position' => 0]);
    $child = Category::create(['name' => 'Bravo', 'parent_id' => $root->id, 'position' => 0]);

    expect($child->treeParentId())->toBe($root->id);
});

it('reports the position as an integer even when the driver returns a string', function () {
    // ⚠️ Research R4. Keys and positions arrive from some drivers as numeric
    // strings. A `treePosition()` that leaked a string would compare unequal to
    // its own value under the strict comparisons the placement rule uses.
    $node = new Category(['position' => '3']);

    expect($node->treePosition())->toBe(3);
});

it('reads its column names from configuration rather than hard-coding them', function () {
    // The Page fixture has NO `parent_id`, NO `position` and NO `name` column.
    // Anything hard-coded to Category's shape cannot survive this (research R9).
    $this->usePageColumns();

    $section = Page::create(['title' => 'Alpha', 'sort_order' => 0]);
    $child = Page::create(['title' => 'Bravo', 'section_id' => $section->id, 'sort_order' => 5]);

    expect(Page::treeParentColumn())->toBe('section_id')
        ->and(Page::treePositionColumn())->toBe('sort_order')
        ->and(Page::treeTiebreakerColumn())->toBe('title');

    expect($child->treeParentId())->toBe($section->id)
        ->and($child->treePosition())->toBe(5)
        ->and($section->treeParentId())->toBeNull();
});

it('falls back to the key when no tie-breaker is configured', function () {
    config(['tree.tiebreaker' => null]);

    expect(Category::treeTiebreakerColumn())->toBeNull();
});

it('supplies the recursive descendant relationship the cycle guard needs', function () {
    // The one genuinely recursive question the package asks: is this destination
    // inside my own subtree? (research R12). Proven here against real rows rather
    // than assumed from the dependency's README.
    $root = Category::create(['name' => 'Alpha', 'position' => 0]);
    $child = Category::create(['name' => 'Bravo', 'parent_id' => $root->id, 'position' => 0]);
    $grandchild = Category::create(['name' => 'Charlie', 'parent_id' => $child->id, 'position' => 0]);
    $unrelated = Category::create(['name' => 'Delta', 'position' => 1]);

    $keys = $root->descendantsAndSelf()->pluck('id')->map(intval(...))->sort()->values()->all();

    expect($keys)->toBe([$root->id, $child->id, $grandchild->id]);
    expect($keys)->not->toContain($unrelated->id);
});

it('uses the configured parent column for the recursive relationship too', function () {
    // ⚠️ The seam most likely to be half-wired: `treeParentId()` reading config
    // while the adjacency-list package still walks `parent_id`. The Page fixture
    // has no such column, so a half-wired trait produces an SQL error here.
    $this->usePageColumns();

    $root = Page::create(['title' => 'Alpha', 'sort_order' => 0]);
    $child = Page::create(['title' => 'Bravo', 'section_id' => $root->id, 'sort_order' => 0]);

    $keys = $root->descendantsAndSelf()->pluck('id')->map(intval(...))->sort()->values()->all();

    expect($keys)->toBe([$root->id, $child->id]);
});

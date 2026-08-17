<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * PA-13 — the two questions the markup must keep apart.
 *
 *   data-ltree-locked     -> isValidTreeTarget(): may this node RECEIVE children?
 *   data-ltree-immovable  -> canMoveNode():       may THIS ACTOR move this node?
 *
 * ⚠️ The package had only the first and used it to answer the second. The browser
 * suite proves what the keyboard then does; these cases prove the two flags are
 * rendered from two different slots — because a package that answered both from one
 * would pass every behavioural test while leaving the conflation in place.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;
});

it('marks no row immovable for a host that does not implement the slot', function () {
    Category::create(['name' => 'Delta', 'position' => 0]);

    expect(Livewire::test(CategoryTreePage::class)->html())
        ->not->toContain('data-ltree-immovable');
});

it('marks the row the host named', function () {
    CategoryTreePage::$immovableName = 'Delta';

    $delta = Category::create(['name' => 'Delta', 'position' => 0]);
    Category::create(['name' => 'Alpha', 'position' => 1]);

    $html = Livewire::test(CategoryTreePage::class)->html();

    expect($html)->toContain('data-ltree-immovable');

    // Only that one: a slot answering for the whole page would pass the case above.
    expect(substr_count($html, 'data-ltree-immovable'))->toBe(1);
    expect($html)->toMatch('/data-ltree-immovable="true"[^>]*data-ltree-key="'.$delta->id.'"|data-ltree-key="'.$delta->id.'"[^>]*data-ltree-immovable="true"/s');
});

it('does not mark an immovable row as locked', function () {
    // ⚠️ The whole point. `is_active` is untouched here, so the node may still
    // receive children — only its own movement is refused.
    CategoryTreePage::$immovableName = 'Delta';

    Category::create(['name' => 'Delta', 'position' => 0, 'is_active' => true]);
    Category::create(['name' => 'Alpha', 'position' => 1]);

    expect(Livewire::test(CategoryTreePage::class)->html())
        ->toContain('data-ltree-immovable')
        ->not->toContain('data-ltree-locked');
});

it('does not mark a locked row as immovable', function () {
    // The other direction: a node closed to new children is still the actor's to
    // reorder, and the package used to refuse moving it at all.
    Category::create(['name' => 'Delta', 'position' => 0, 'is_active' => false]);
    Category::create(['name' => 'Alpha', 'position' => 1]);

    expect(Livewire::test(CategoryTreePage::class)->html())
        ->toContain('data-ltree-locked')
        ->not->toContain('data-ltree-immovable');
});

<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * F24 — "nothing here" and "nothing matches your search" are DIFFERENT sentences.
 *
 * ⚠️ Raised by the first consumer, which has had two strings for these since
 * before the package existed:
 *
 *   no_matches -> 'No categories match your search.'
 *   empty      -> 'No categories yet. Create the first one.'
 *
 * The package rendered ONE `empty` string whenever the displayed set was empty and
 * gave a host no way to tell the two apart — it neither passed the search state to
 * the view nor offered a second key. The consumer had to override `empty` with its
 * search wording, because a frozen assertion pins that, which left a genuinely
 * empty tree reading "No categories match your search." with no search active.
 *
 * ⚠️ The one regression in that adoption with NO host-side fix, which is what makes
 * it the package's problem rather than the host's.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
});

it('says the tree is empty when there is nothing in it', function () {
    expect(Livewire::test(CategoryTreePage::class)->instance()->treeEmptyMessage())
        ->toBe('Nothing to show.');
});

it('says nothing MATCHES when a search excludes everything', function () {
    // ⚠️ The tree is NOT empty here — the actor's search is what emptied the view.
    // Telling them the tree is empty would be false, and it is the sentence a
    // first-time user should see instead.
    Category::create(['name' => 'Delta', 'position' => 0]);

    expect(Livewire::test(CategoryTreePage::class)->set('treeSearch', 'zzz-nothing')->instance()->treeEmptyMessage())
        ->toBe('Nothing matches that search.');
});

it('goes back to the empty wording when the search is cleared', function () {
    Category::create(['name' => 'Delta', 'position' => 0]);

    expect(Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'zzz-nothing')
        ->set('treeSearch', '')
        ->instance()->treeEmptyMessage())->toBe('Nothing to show.');
});

it('treats a whitespace-only search as no search at all', function () {
    // ⚠️ The same `trim()` the search path itself uses. Two different answers to
    // "is a search active?" in one class is how these drift apart.
    expect(Livewire::test(CategoryTreePage::class)->set('treeSearch', '   ')->instance()->treeEmptyMessage())
        ->toBe('Nothing to show.');
});

it('renders the message the page chose, not the raw empty key', function () {
    Category::create(['name' => 'Delta', 'position' => 0]);

    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'zzz-nothing')
        ->assertSee('Nothing matches that search.');
});

it('lets a host override the two states separately', function () {
    // The whole point: a host with two sentences can keep both.
    app('translator')->addLines([
        'tree.empty' => 'HOST nothing yet',
        'tree.empty_search' => 'HOST no matches',
    ], 'en', 'tree');

    Category::create(['name' => 'Delta', 'position' => 0]);

    expect(Livewire::test(CategoryTreePage::class)->instance()->treeEmptyMessage())->toBe('HOST nothing yet');
    expect(Livewire::test(CategoryTreePage::class)->set('treeSearch', 'zzz')->instance()->treeEmptyMessage())
        ->toBe('HOST no matches');
});

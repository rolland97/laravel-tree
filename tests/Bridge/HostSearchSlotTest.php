<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\SearchSlotTreePage;

/**
 * PA-5 — `matchesSearch()` is a documented HOST SLOT.
 *
 * It was already `protected`, and therefore already overridable. The amendment is
 * that it is now in the member table: overriding an undocumented internal is a
 * private dependency on something that may move without a major version, and the
 * first real consumer has to override it (the consumer's adoption, research F6 — it
 * searches name OR `short_code`, and its placeholder copy promises exactly that).
 *
 * ⚠️ **This guard's red is a MUTATION red, not an absence red.** The slot worked
 * before the amendment, so nothing here could fail for want of an implementation.
 * What it discriminates is whether the search path still ASKS the host: bypassing
 * `matchesSearch()` inside `readSearchVisibleIds()` — comparing the tie-breaker
 * column inline, which is what the default does anyway — turns this file red while
 * every other test in the suite stays green. Recorded in the validation log.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    // ⚠️ `short_code` deliberately shares NO substring with `name`, or a package
    // that ignored the override would still find the row by name and pass.
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0, 'short_code' => 'ZZ-100']);
    $this->charlie = Category::create([
        'name' => 'Charlie',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'short_code' => 'QQ-200',
    ]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
});

afterEach(function () {
    CategoryTreePage::$moveAuthorization = 'allow';
});

it('documents matchesSearch as an overridable member of the trait', function () {
    $method = new ReflectionMethod(InteractsWithTree::class, 'matchesSearch');

    // `protected`, not `private` — a private method cannot be overridden at all,
    // so promoting it to the member table would be promising something false.
    expect($method->isProtected())->toBeTrue();
    expect($method->isFinal())->toBeFalse();
});

it('honours a host that searches a column the package knows nothing about', function () {
    // ⚠️ `QQ-200` appears in no `name`. Finding Charlie can only mean the host's
    // own rule was consulted.
    Livewire::test(SearchSlotTreePage::class)
        ->set('treeSearch', 'QQ-200')
        ->assertSee('Charlie')
        ->assertDontSee('Bravo');
});

it('keeps a match found through the host column reachable by showing its ancestors', function () {
    // The ancestor walk must not depend on WHICH rule matched — a match found by
    // the host's second column is as buried as one found by name.
    Livewire::test(SearchSlotTreePage::class)
        ->set('treeSearch', 'QQ-200')
        ->assertSee('Delta');
});

it('does not find that row without the override, so the guard is not vacuous', function () {
    // ⚠️ The half that makes the test above mean something. The same term against
    // the package's DEFAULT rule finds nothing, because it searches `name` only.
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'QQ-200')
        ->assertDontSee('Charlie')
        ->assertDontSee('Delta');
});

it('still searches the tie-breaker column by default', function () {
    // PA-5 documents a slot; it must not change what the package does when no
    // host overrides it.
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'Charlie')
        ->assertSee('Charlie')
        ->assertDontSee('Bravo');
});

it('never widens what the host made visible, whatever the host search matches', function () {
    // ⚠️ Search NARROWS what is displayed and must never WIDEN what is visible.
    // A host slot is the obvious place to get that wrong: this override could have
    // been written against a fresh query rather than the visible set.
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
        'short_code' => 'QQ-200',
    ]);

    Livewire::test(SearchSlotTreePage::class)
        ->set('treeSearch', 'QQ-200')
        ->assertDontSee('Aardvark');

    expect($hidden->fresh()->is_visible)->toBeFalse();
});

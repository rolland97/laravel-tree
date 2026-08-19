<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\ComposedTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\ComposedUnorderedTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\OrderedTwinTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\SpikePage;

/**
 * PA-19 — the tree is includable, so a host can put it in its own layout.
 *
 * ⚠️ The package shipped exactly two views and `tree.blade.php` opened with
 * `<x-filament-panels::page>`, so the tree WAS a page rather than something a host
 * could place beside anything else. `getView()` was overridable, but a host view
 * would then have had to reproduce the controller wrapper, the live region, the
 * search toolbar and the confirmation — and `README.md` says the package's blades
 * are not a public API. So the supported answer was "you cannot", which is what the
 * second consumer needed to do (074 FR-001: breadcrumb, sidebar, contents pane).
 *
 * ⚠️ Same shape as PA-1, which made the tree a TRAIT because a host needed a
 * different class shell. This makes its markup includable because a host needs a
 * different layout shell. One body, many shells.
 *
 * ⚠️ **C2 — that `tree::tree` still renders what it always did — is proven by the
 * EXISTING suites passing untouched.** No case here restates it. A regression guard's
 * value is that it never went red, and duplicating it into this file would only give
 * two places to weaken.
 */
beforeEach(function () {
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

// ── C1 — the partial is content, not a page ──────────────────────────────────

it('puts the tree INSIDE the host layout rather than replacing it', function () {
    $html = Livewire::test(ComposedTreePage::class)->html();

    // The host's own regions survive…
    expect($html)->toContain('data-host-region="breadcrumb"')
        ->and($html)->toContain('data-host-region="pane"')
        // …and the tree is inside the host's sidebar region, not instead of the page.
        ->and($html)->toMatch('/data-host-region="sidebar".*?role="tree"/s');
});

it('does not nest a second page wrapper inside the host page', function () {
    // ⚠️ THE failure C1 forbids: if `tree-content` still opened a page component, a
    // host including it inside its own page would render one page inside another.
    //
    // ⚠️ Compared against `SpikePage`, which is a plain Filament page that does NOT
    // include the tree. That baseline is the whole point, and it was got wrong first:
    // the guard originally compared the composed page against `OrderedTwinTreePage`,
    // and BOTH of them render the partial — so nesting doubled both counts equally and
    // the guard could not see it. Measured under the mutation: 8 and 8. A comparison is
    // only a guard when the two sides do not share the thing being mutated.
    $composed = substr_count(Livewire::test(ComposedTreePage::class)->html(), 'fi-page');
    $onePage = substr_count(Livewire::test(SpikePage::class)->html(), 'fi-page');

    expect($composed)->toBe($onePage);
});

it('ships a content partial that opens no page component of its own', function () {
    // ⚠️ A STRUCTURAL guard on the package's own file, deliberately alongside the
    // rendered one above. C1 is a claim about what this file IS, and asserting it
    // directly cannot be fooled by however a future Filament version marks a page.
    $partial = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/tree-content.blade.php');

    // ⚠️ Blade comments stripped first. The file's own docblock shows a host how to
    // compose the tree, and that example necessarily contains the page component —
    // so a naive `not->toContain` fails on the documentation rather than the markup.
    // Caught by this guard going red against correct code.
    $markup = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $partial);

    expect($markup)->not->toContain('<x-filament-panels::page>');
});

// ── C3 / C4 — the composed host gets a WORKING tree ──────────────────────────

it('carries the Alpine controller into the composed layout', function () {
    // ⚠️ THE thing a host could not have reproduced. Without the controller the rows
    // render and nothing responds — arrows, expand, search-reveal, all dead.
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('x-data="ltree(');
});

it('carries the live region into the composed layout', function () {
    // ⚠️ `wire:ignore` and all. Its content is client state the server knows nothing
    // about, so a re-render wipes a region that lost that attribute (AGENTS.md R-017)
    // — and a host reproducing this by hand is exactly how that gets forgotten.
    $html = Livewire::test(ComposedTreePage::class)->html();

    expect($html)->toContain('ltree-live-region')
        ->and($html)->toContain('wire:ignore')
        ->and($html)->toContain('aria-live="polite"');
});

it('carries the search toolbar into the composed layout', function () {
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('type="search"');
});

it('renders the rows themselves in the composed layout', function () {
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('data-ltree-key="'.$this->delta->id.'"')
        ->toContain('Charlie');
});

// ── C5 — every ARIA guarantee survives the move ──────────────────────────────

it('keeps every ARIA property the uncomposed host renders', function () {
    // ⚠️ The composed page differs from `OrderedTwinTreePage` in its LAYOUT alone —
    // it extends it — so any difference in the tree's own ARIA can only have come
    // from the split. Names, not values: ids and counts differ between renders for
    // reasons that have nothing to do with this amendment.
    $composed = Livewire::test(ComposedTreePage::class)->html();
    $plain = Livewire::test(OrderedTwinTreePage::class)->html();

    expect(ariaNamesIn($composed))->toBe(ariaNamesIn($plain));
    expect(roleValuesIn($composed))->toContain('tree', 'treeitem', 'group');
});

it('still names each row by its own name alone in the composed layout', function () {
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('aria-labelledby="ltree-name-'.$this->delta->id.'"');
});

// ── C6 — PA-18 still works once composed ─────────────────────────────────────

it('still retires ordering for a composed host that asks for it', function () {
    // ⚠️ The two amendments must hold TOGETHER. PA-18's answer reaches the controller
    // as the third `x-data` argument, and PA-19 moves the element carrying it into a
    // different file — so a split that dropped it would leave a page with no handle
    // whose keyboard still picked rows up.
    $html = Livewire::test(ComposedUnorderedTreePage::class)->html();

    expect($html)->not->toContain('data-ltree-handle')
        ->and($html)->not->toContain('draggable="true"')
        // The controller must still be told, not merely the markup stripped.
        ->and($html)->toContain('x-data="ltree(');
});

it('still offers ordering to a composed host that says nothing', function () {
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('data-ltree-handle');
});

// ── C7 — PA-8 and PA-11 still work once composed ─────────────────────────────

it('still honours the initial collapse state in the composed layout', function () {
    expect(Livewire::test(ComposedTreePage::class)->html())
        ->toContain('aria-expanded="true"');
});

it('still reveals a search match in the composed layout', function () {
    $page = Livewire::test(ComposedTreePage::class)->set('treeSearch', 'Charlie');

    expect($page->html())
        ->toContain('Charlie')
        ->toContain('Delta')
        ->not->toContain('Alpha');
});

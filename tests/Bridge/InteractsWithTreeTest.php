<?php

declare(strict_types=1);

use Filament\Pages\Page as PanelPage;
use Filament\Resources\Pages\Page as ResourcePage;
use Livewire\Livewire;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\TreeCategories;
use Rolland\Tree\Tests\Stored;

/**
 * PA-1 — the tree is a TRAIT, so a resource-page host can have it too.
 *
 * ⚠️ Requested by the first real consumer (the consumer's adoption, research F1). Its tree
 * is a resource INDEX page: `VendorCategoryResource::getPages()` registers
 * `'index' => TreeVendorCategories::route('/')`, and `route()` is declared only on
 * `Filament\Resources\Pages\Page`. `TreePage extends Filament\Pages\Page`, so a
 * host could not inherit both — and PHP has no second inheritance slot.
 *
 * ⚠️ **F1's stated mechanism is wrong and the conclusion is right.** It records the
 * two page classes as "siblings under BasePage". They are not: `Resources\Pages\Page
 * extends Filament\Pages\Page as BasePage`, so the resource page is a DESCENDANT of
 * the panel page. The conclusion survives unchanged, because inheritance only runs
 * one way — `route()` lives on the child, and extending `TreePage` lands a host on
 * the parent, where it does not exist. Recorded per AGENTS.md R-037.
 */
beforeEach(function () {
    // Positional order disagrees with alphabetical at every level (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

// ── The trait exists and carries the tree ────────────────────────────────────

it('offers the tree as a trait, not only as a base page', function () {
    expect(trait_exists(InteractsWithTree::class))->toBeTrue();
});

it('builds TreePage out of the same trait, so the two hosts cannot drift', function () {
    // ⚠️ If TreePage kept its own copy of the body there would be two
    // implementations of the placement contract, and the resource-page host would
    // be the one that silently fell behind.
    expect(class_uses(TreePage::class))->toContain(InteractsWithTree::class);
});

// ── A resource-page host is possible at all — the point of PA-1 ──────────────

it('lets a resource index page host the tree', function () {
    expect(is_subclass_of(TreeCategories::class, ResourcePage::class))->toBeTrue();
    expect(class_uses(TreeCategories::class))->toContain(InteractsWithTree::class);

    // ⚠️ The thing that was impossible before: this host is NOT a TreePage.
    expect(is_subclass_of(TreeCategories::class, TreePage::class))->toBeFalse();
});

it('keeps the resource registration that extending TreePage would have cost', function () {
    // `route()` is why this amendment exists. A panel page does not have it.
    expect(method_exists(TreeCategories::class, 'route'))->toBeTrue();
    expect(method_exists(PanelPage::class, 'route'))->toBeFalse();
});

// ── It is the same tree, not a lookalike ─────────────────────────────────────

it('renders the hierarchy the host made visible from a resource page', function () {
    Livewire::test(TreeCategories::class)
        ->assertSee('Delta')
        ->assertSee('Charlie')
        ->assertSee('Bravo')
        ->assertSee('Alpha');
});

it('groups nodes under their parent from a resource page host', function () {
    $grouped = Livewire::test(TreeCategories::class)->instance()->nodesByParent();

    expect(array_map(fn ($n) => $n->name, $grouped[''] ?? []))->toBe(['Delta', 'Alpha']);
    expect(array_map(fn ($n) => $n->name, $grouped[(string) $this->delta->id] ?? []))
        ->toBe(['Charlie', 'Bravo']);
});

it('commits a move from a resource page host', function () {
    Livewire::test(TreeCategories::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(Stored::order($this->delta->id))->toBe([$this->bravo->id, $this->charlie->id]);
});

// ── The documented collision, checked rather than assumed ────────────────────

it('resolves getHeaderActions() to the trait, not to the Filament concern it inherits', function () {
    // ⚠️ PA-1 asks for this explicitly. `getHeaderActions()` is protected on
    // `Filament\Pages\Concerns\InteractsWithHeaderActions`, which BOTH base pages
    // use, and also on the tree trait. PHP resolves a trait method ahead of an
    // INHERITED one — so the trait wins — but "the trait wins" is a claim about
    // language semantics that this package would otherwise be taking on trust.
    $declaring = (new ReflectionMethod(TreeCategories::class, 'getHeaderActions'))
        ->getDeclaringClass()
        ->getName();

    // A trait's methods are flattened into the composing class, so the trait
    // winning means TreeCategories declares it. Losing would name the Filament
    // page the method was inherited from.
    expect($declaring)->toBe(TreeCategories::class);
});

it('reaches the host header-action slot through the trait', function () {
    // The behavioural half: the reflection above proves which method is bound,
    // this proves the bound one actually calls the host's slot.
    Livewire::test(TreeCategories::class)->assertSee('New root from the resource page');
});

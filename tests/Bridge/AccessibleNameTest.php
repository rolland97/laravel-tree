<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\NamedTreePage;

/**
 * PA-7 — the TREE's accessible name is the host's to choose.
 *
 * ⚠️ The package rendered `<div role="tree" aria-label="{{ static::getNavigationLabel() }}">`,
 * so the tree's accessible name WAS the navigation label. A host with two strings
 * for those two jobs could not have both: the only way to rename the tree was to
 * rename its navigation item (the consumer's adoption, T048).
 *
 * ⚠️ This sits inside the package's own accessibility remit and got neither the
 * care nor the slot the ROWS got. R-014 is about a row being announced by its own
 * name; R-013 about the ARIA properties sitting on the focusable element. The
 * control's own name was named by whatever the sidebar said.
 *
 * ⚠️ Its red is an ABSENCE red for the override and a REGRESSION red for the
 * default: the second case here passes before the amendment and must keep passing
 * after it, because a host that has not overridden anything may not move.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    Category::create(['name' => 'Delta', 'position' => 0]);
});

it('names the tree with what the host said, not with the navigation label', function () {
    expect(Livewire::test(NamedTreePage::class)->html())
        ->toContain('role="tree" aria-label="The category hierarchy"');
});

it('leaves the navigation label alone — the two are different jobs', function () {
    // ⚠️ The whole point of the amendment. If naming the tree cost the host its
    // navigation label, this slot would be a rename with extra steps.
    expect(NamedTreePage::getNavigationLabel())->toBe('Named tree');

    expect(Livewire::test(NamedTreePage::class)->html())
        ->not->toContain('role="tree" aria-label="Named tree"');
});

it('still falls back to the navigation label for a host that says nothing', function () {
    // ⚠️ REGRESSION guard, not a new promise: every existing host keeps the name it
    // has today. "Migration: none" is a claim, and this is the assertion behind it.
    expect(Livewire::test(CategoryTreePage::class)->html())
        ->toContain('role="tree" aria-label="Category tree"');
});

it('defaults the slot itself to the navigation label', function () {
    expect(Livewire::test(CategoryTreePage::class)->instance()->treeAccessibleName())
        ->toBe(CategoryTreePage::getNavigationLabel());
});

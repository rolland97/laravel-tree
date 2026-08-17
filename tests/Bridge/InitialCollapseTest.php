<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\CollapsedTreePage;

/**
 * PA-8 — whether branches start OPEN or CLOSED is the host's to choose.
 *
 * ⚠️ Not cosmetic. The initial state decides what the arrow keys traverse, what a
 * screen reader walks, and how many rows a large tree paints at once. The package
 * offered no slot and answered "open" for everyone (the consumer's adoption, T048).
 *
 * ⚠️ The SERVER-rendered markup has to agree with the controller's answer, which is
 * what these cases pin. A page that renders `aria-expanded="true"` and lets Alpine
 * correct it after boot announces the wrong state to anything reading the document
 * before then — and it flashes every descendant of every branch on first paint.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
});

it('starts branches open for a host that says nothing', function () {
    // ⚠️ REGRESSION guard. "Migration: none" is a claim; this is its evidence.
    expect(Livewire::test(CategoryTreePage::class)->instance()->treeBranchesStartCollapsed())->toBeFalse();

    expect(Livewire::test(CategoryTreePage::class)->html())
        ->toContain('aria-expanded="true"');
});

it('renders a closed branch as closed when the host asks for it', function () {
    expect(Livewire::test(CollapsedTreePage::class)->html())
        ->toContain('aria-expanded="false"')
        ->not->toContain('aria-expanded="true"');
});

it('hides the children of a closed branch in the served markup', function () {
    // Before Alpine boots, `x-show` has done nothing at all — so the initial state
    // has to be in the markup the server sent.
    expect(Livewire::test(CollapsedTreePage::class)->html())
        ->toContain('data-ltree-children-of="'.$this->delta->id.'"')
        ->toContain('display: none');
});

it('keeps a closed branch\'s children IN the DOM', function () {
    // ⚠️ `x-show`, never `x-if`. A child of a closed branch is still a member of
    // its group, and if closing a branch removed rows, the rendered set the client
    // reports back would depend on what the actor happened to have open — the
    // client-index defect of constitution Principle II, arriving by another door.
    expect(Livewire::test(CollapsedTreePage::class)->html())->toContain('Charlie');
});

it('leaves an open host\'s children unhidden', function () {
    expect(Livewire::test(CategoryTreePage::class)->html())->not->toContain('display: none');
});

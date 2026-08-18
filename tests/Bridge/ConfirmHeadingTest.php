<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\ConfirmHeadingTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * PA-6 — a host may name its own confirmation, instead of having its question
 * demoted into the body under this package's generic heading.
 *
 * ⚠️ Raised from a live walk, not from a reading. The first consumer composed a
 * title, a body and an affected-counts line into the ONE string the slot accepted,
 * and the rendered result carried two headings: the package's *"Confirm this move"*
 * in the heading slot, and the actor's real question — *"Make this branch
 * private?"* — buried as the first sentence of the paragraph (the consumer's adoption,
 * T058).
 *
 * ⚠️ `CategoryTreePage` still returns a plain string on purpose. The string form is
 * the whole installed base, and the guard below that pins it is the one that would
 * catch an amendment that "supports both" by breaking the old shape.
 */
beforeEach(function () {
    // ⚠️ Stated, not assumed — CategoryTreePage::$hideBravo is a process-global
    // static, and this file reads a tree that must contain Bravo (finding F37).
    CategoryTreePage::$hideBravo = false;

    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

/** Queue the cross-parent move both fixtures ask to confirm. */
function queueConfirmableMove(string $page, object $test): object
{
    return Livewire::test($page)->call(
        'placeNode',
        $test->alpha->id,
        $test->delta->id,
        $test->charlie->id,
        SiblingPlacement::After->name,
        [$test->charlie->id, $test->bravo->id],
    );
}

it('renders the HOST heading when the host supplies one', function () {
    queueConfirmableMove(ConfirmHeadingTreePage::class, $this)
        ->assertSee('Make this branch private?')
        ->assertSee('Moving Alpha will move everything underneath it.');
});

it('does not also render the package heading once the host has supplied one', function () {
    // ⚠️ The defect was never a MISSING host heading — the consumer's words were on
    // screen the whole time. It was two headings, with the generic one winning the
    // visual hierarchy. A guard that only asserted the host's text would have
    // passed against the broken markup.
    queueConfirmableMove(ConfirmHeadingTreePage::class, $this)
        ->assertDontSee('Confirm this move');
});

it('still renders the package heading for a host that returns a plain string', function () {
    queueConfirmableMove(CategoryTreePage::class, $this)
        ->assertSee('Confirm this move')
        ->assertSee('Moving Alpha will move everything underneath it.');
});

it('writes nothing until an array-form confirmation is answered', function () {
    $before = Stored::order($this->delta->id);

    queueConfirmableMove(ConfirmHeadingTreePage::class, $this)
        ->assertSet('pendingMove.nodeKey', $this->alpha->id);

    expect(Stored::order($this->delta->id))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

it('commits an array-form move without carrying the heading into the payload', function () {
    // ⚠️ The heading rides in `pendingMove` alongside the raw payload, exactly as
    // the message already does, and `commit()` must not see either. If the unset is
    // forgotten the extra key reaches the resolver, so this asserts the WRITE
    // rather than the absence of a key.
    queueConfirmableMove(ConfirmHeadingTreePage::class, $this)
        ->call('confirmPendingMove');

    expect($this->alpha->fresh()->parent_id)->toBe($this->delta->id);
    expect(Stored::order($this->delta->id))
        ->toBe([$this->charlie->id, $this->alpha->id, $this->bravo->id]);
});

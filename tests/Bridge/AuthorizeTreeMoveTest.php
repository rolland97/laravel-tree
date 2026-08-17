<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\RecordingTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * PA-2 — the host's PERMISSION slot. ⚠️ **This one is a security fix.**
 *
 * Before it, `authorizeMove()` resolved ids through the host's `visibleQuery()`
 * and did nothing else, and **visibility is not permission**. The first real
 * consumer calls `authorize('update', $category)` on every committing path and
 * pins an actor holding `view` and NOT `update` being refused with nothing moved
 * (the consumer's keyboard-order test `Test:152`). Adopted as it stood, that
 * actor's move would have SUCCEEDED.
 *
 * ⚠️ The public contract already claimed this worked — *"`placeNode()` re-checks
 * the host's authorization on the committing call"*. It re-checked the host's
 * **visibility**. A documented guarantee the code does not provide is worse than a
 * missing one, so the wording was part of the amendment.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    // Positional: Delta(0) [ Charlie(0), Bravo(1) ], Alpha(1)
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

afterEach(function () {
    // ⚠️ Restored here, not merely set in beforeEach. `phpunit.xml.dist` runs in
    // RANDOM order (AGENTS.md R-029) and this is a process-global static, so a
    // leaked `deny` would refuse moves in whichever unrelated file ran next.
    CategoryTreePage::$moveAuthorization = 'allow';
});

// ── The slot exists and defaults to permitting ───────────────────────────────

it('offers the host a permission slot distinct from its visibility scope', function () {
    expect(method_exists(InteractsWithTree::class, 'authorizeTreeMove'))->toBeTrue();
});

it('permits the move when the host does not answer', function () {
    // ⚠️ The half that stops every other test here passing vacuously: if the
    // package refused everything, the refusal assertions below would all pass.
    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(Category::find($this->bravo->id)->position)->toBe(0);
});

// ── A refusal writes nothing ─────────────────────────────────────────────────

it('writes nothing when the host refuses the move', function () {
    $before = Stored::order($this->delta->id);

    CategoryTreePage::$moveAuthorization = 'deny';

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(Stored::order($this->delta->id))->toBe($before);
});

it('does not queue a confirmation for a move the host refuses', function () {
    // An actor who may not make the move should not be shown a modal asking them
    // to confirm it.
    CategoryTreePage::$moveAuthorization = 'deny';

    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->alpha->id,
            $this->delta->id,
            $this->charlie->id,
            SiblingPlacement::After->name,
            [$this->charlie->id, $this->bravo->id],
        )
        ->assertSet('pendingMove', null);

    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

// ── EVERY committing path, not merely the first ──────────────────────────────

it('re-asks the host on the confirming call, not only when the move was queued', function () {
    // ⚠️ THE point of PA-2, and the reason a check made at queue time is not the
    // guard. An actor's permissions can change between queueing a move and
    // confirming it — which is exactly what happens when a role is edited
    // mid-session.
    $test = Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->alpha->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::After->name,
        [$this->charlie->id, $this->bravo->id],
    );

    $test->assertSet('pendingMove.nodeKey', $this->alpha->id);

    CategoryTreePage::$moveAuthorization = 'deny';

    $test->call('confirmPendingMove');

    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

// ── A host that throws gets its 403 ──────────────────────────────────────────

it('lets a host raise its own 403 instead of swallowing it', function () {
    // ⚠️ THE shape the first real consumer needs. It calls
    // `$this->authorize('update', $category)` and its suite asserts the request is
    // FORBIDDEN (`VendorCategoryKeyboardOrderTest:152`). That only works if the
    // package lets the exception out: `commit()` catches `DomainException`, which
    // is the refusal vocabulary, and an `AuthorizationException` is deliberately
    // not one.
    CategoryTreePage::$moveAuthorization = 'throw';

    $before = Stored::order($this->delta->id);

    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->bravo->id,
            $this->delta->id,
            $this->charlie->id,
            SiblingPlacement::Before->name,
            [$this->charlie->id, $this->bravo->id],
        )
        ->assertForbidden();

    expect(Stored::order($this->delta->id))->toBe($before);
});

it('does not report every move as forbidden', function () {
    // ⚠️ The non-vacuity half. `assertForbidden()` reaches Livewire's Testable
    // through `__call` forwarding, so it is worth proving it can still FAIL —
    // otherwise the assertion above would pass against a package that never
    // consulted the host at all.
    $forbidden = true;

    try {
        Livewire::test(CategoryTreePage::class)
            ->call(
                'placeNode',
                $this->bravo->id,
                $this->delta->id,
                $this->charlie->id,
                SiblingPlacement::Before->name,
                [$this->charlie->id, $this->bravo->id],
            )
            ->assertForbidden();
    } catch (Throwable) {
        $forbidden = false;
    }

    expect($forbidden)->toBeFalse();
});

// ── The host is asked about resolved models, never client ids ────────────────

it('asks the host about models it resolved from the host own scope', function () {
    // ⚠️ A slot handed the raw client payload would be asking the host to
    // authorize an id the actor may not even be able to see — pushing the
    // resolution problem back out to every host, which is the shape of the defect
    // this package exists to prevent (constitution Principle II).
    RecordingTreePage::forgetAsked();

    Livewire::test(RecordingTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(RecordingTreePage::$asked)->not->toBeEmpty();

    ['node' => $node, 'parent' => $parent] = RecordingTreePage::$asked[0];

    expect($node)->toBeInstanceOf(Category::class);
    expect($node->getKey())->toBe($this->bravo->id);
    expect($parent)->toBeInstanceOf(Category::class);
    expect($parent->getKey())->toBe($this->delta->id);
});

it('tells the host a root destination apart from an unresolved one', function () {
    // `null` means "the root", and a host asked to authorize a move to the root
    // must be able to distinguish that from a parent it failed to resolve. The
    // package never asks about the latter — it refuses before reaching the slot.
    RecordingTreePage::forgetAsked();

    Livewire::test(RecordingTreePage::class)->call(
        'placeNode',
        $this->charlie->id,
        null,
        $this->delta->id,
        SiblingPlacement::After->name,
        [$this->delta->id, $this->alpha->id],
    );

    expect(RecordingTreePage::$asked)->not->toBeEmpty();
    expect(RecordingTreePage::$asked[0]['parent'])->toBeNull();
});

it('never asks the host about a node the actor cannot see', function () {
    // ⚠️ Order matters: visibility is resolved FIRST. A host asked to authorize a
    // node privacy hides would have to re-implement the visibility check to answer
    // safely — and a host that answered `true` would reopen the hole.
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
    ]);

    RecordingTreePage::forgetAsked();

    Livewire::test(RecordingTreePage::class)->call(
        'placeNode',
        $hidden->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(RecordingTreePage::$asked)->toBeEmpty();
});

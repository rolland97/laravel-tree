<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * US2 (T048–T052) — the pointer story's server half.
 *
 * ⚠️ Fixture names disagree with positional order throughout, for the same reason
 * as the core suite: the read tie-breaks by name, so a carelessly-named group lets
 * a wrong implementation produce the right visible output (AGENTS.md R-025).
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    // Positional: Delta(0) [ Charlie(0), Bravo(1) ], Alpha(1)
    // Alphabetical would be the reverse at every level.
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);

    $this->hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
    ]);
});

// ── T048 — the hierarchy renders ─────────────────────────────────────────────

it('renders the whole hierarchy the host made visible', function () {
    Livewire::test(CategoryTreePage::class)
        ->assertSee('Delta')
        ->assertSee('Charlie')
        ->assertSee('Bravo')
        ->assertSee('Alpha');
});

it('renders nothing the host did not make visible', function () {
    // ⚠️ The package never adds a privacy scope, and must never widen the host's.
    // `Aardvark` is a real child of Delta; the host's visibleQuery excludes it.
    Livewire::test(CategoryTreePage::class)->assertDontSee('Aardvark');
});

it('nests children under their parent rather than flattening the tree', function () {
    $grouped = Livewire::test(CategoryTreePage::class)->instance()->nodesByParent();

    expect(array_map(fn ($n) => $n->name, $grouped[''] ?? []))->toBe(['Delta', 'Alpha']);
    expect(array_map(fn ($n) => $n->name, $grouped[(string) $this->delta->id] ?? []))
        ->toBe(['Charlie', 'Bravo']);
});

// ── T049 — every host slot appears ───────────────────────────────────────────

it('renders host-supplied badges', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('badge-for-Delta');
});

it('renders host-supplied row actions', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('Inspect Delta');
});

it('renders host-supplied header actions', function () {
    Livewire::test(CategoryTreePage::class)->assertSee('New root category');
});

it('renders the host leaf slot only under the node it was returned for', function () {
    // A leaf slot is how a host contributes non-node rows — its own records
    // beneath a category. The package has no idea what they are.
    Livewire::test(CategoryTreePage::class)->assertSee('leaf-slot-for-Delta');
});

// ── T050 — quick search ──────────────────────────────────────────────────────

it('narrows the displayed rows to a search term', function () {
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'Charlie')
        ->assertSee('Charlie')
        ->assertDontSee('Alpha');
});

it('keeps a matched node reachable by also displaying its ancestors', function () {
    // A match buried three levels down is useless if its parents vanish.
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'Charlie')
        ->assertSee('Delta');
});

it('never surfaces an invisible node through the search', function () {
    // ⚠️ Search narrows what is displayed; it must not WIDEN what is visible.
    Livewire::test(CategoryTreePage::class)
        ->set('treeSearch', 'Aardvark')
        ->assertDontSee('Aardvark');
});

// ── T051 — the confirmation flow ─────────────────────────────────────────────

it('applies a move immediately when the host asks for no confirmation', function () {
    // Same-parent reorder — the fixture page returns null from confirmationFor().
    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    // ⚠️ The expected order here is NOT [Bravo, Aardvark, Charlie], which is what
    // "put Bravo before Charlie" looks like if you only count the rows the actor
    // saw. The complete group reads Aardvark(0), Charlie(0), Bravo(1) — Aardvark
    // shares Charlie's position and wins the name tie-break — so "before Charlie"
    // is index 1 of the COMPLETE group, and Bravo lands after the row the actor
    // was never shown.
    //
    // This assertion was wrong when first written, in precisely the direction the
    // 071 defect goes. Left corrected rather than quietly adjusted, because it is
    // the whole point of resolving against the complete group.
    expect(Stored::order($this->delta->id))
        ->toBe([$this->hidden->id, $this->bravo->id, $this->charlie->id]);
});

it('writes NOTHING until a move requiring confirmation is confirmed', function () {
    $before = Stored::order($this->delta->id);

    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->alpha->id,
            $this->delta->id,
            $this->charlie->id,
            SiblingPlacement::After->name,
            [$this->charlie->id, $this->bravo->id],
        )
        ->assertSet('pendingMove.nodeKey', $this->alpha->id);

    expect(Stored::order($this->delta->id))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

it('applies the move once it is confirmed', function () {
    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->alpha->id,
            $this->delta->id,
            $this->charlie->id,
            SiblingPlacement::After->name,
            [$this->charlie->id, $this->bravo->id],
        )
        ->call('confirmPendingMove');

    expect($this->alpha->fresh()->parent_id)->toBe($this->delta->id);
});

it('writes nothing when a pending move is cancelled', function () {
    $before = Stored::order($this->delta->id);

    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->alpha->id,
            $this->delta->id,
            $this->charlie->id,
            SiblingPlacement::After->name,
            [$this->charlie->id, $this->bravo->id],
        )
        ->call('cancelPendingMove')
        ->assertSet('pendingMove', null);

    expect(Stored::order($this->delta->id))->toBe($before);
    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

// ── T052 — authorization is re-checked ON THE COMMITTING CALL ────────────────

it('refuses to move a node the actor cannot currently see', function () {
    // ⚠️ `Aardvark` is invisible to this actor. A tampered payload naming it must
    // change nothing, however the page was rendered.
    $before = Stored::order($this->delta->id);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->hidden->id,
        null,
        $this->delta->id,
        SiblingPlacement::After->name,
        [$this->delta->id, $this->alpha->id],
    );

    expect(Stored::order($this->delta->id))->toBe($before);
    expect($this->hidden->fresh()->parent_id)->toBe($this->delta->id);
});

it('refuses a reference the actor cannot currently see, even if the client sent it', function () {
    $before = Stored::order($this->delta->id);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->charlie->id,
        $this->delta->id,
        $this->hidden->id,
        SiblingPlacement::After->name,
        // A tampered rendered list, naming a row this actor was never shown.
        [$this->charlie->id, $this->bravo->id, $this->hidden->id],
    );

    expect(Stored::order($this->delta->id))->toBe($before);
});

it('re-checks authorization at commit time, not only when the move was queued', function () {
    // ⚠️ THE point of T052. The keyboard and the pointer both refuse early as a
    // courtesy; that refusal is not the guard. Here the actor's scope shrinks
    // between queueing the move and confirming it, which is exactly what happens
    // when permissions change mid-session.
    $test = Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->alpha->id,
        $this->delta->id,
        $this->bravo->id,
        SiblingPlacement::After->name,
        [$this->charlie->id, $this->bravo->id],
    );

    CategoryTreePage::$hideBravo = true;

    $test->call('confirmPendingMove');

    expect($this->alpha->fresh()->parent_id)->toBeNull();
});

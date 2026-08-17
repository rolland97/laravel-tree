<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * PA-3 — a SAME-PARENT placement is a reorder, and routes through `ReorderSiblings`.
 *
 * Before this, `commit()` always went `PlaceNode` → `MoveNode` → `NodeMoved`, for a
 * same-parent reorder as much as for a re-parent. Two consequences, both real:
 *
 * 1. `SiblingsReordered` had **no producer anywhere in the bridge** — a public
 *    event nothing fired, which is why `grep -rn 'ReorderSiblings' src/ resources/`
 *    found no caller outside the action's own file (the consumer's adoption, research F4).
 * 2. A host's audit trail recorded a drag or keyboard reorder as `moved` rather
 *    than `reordered`, losing the whole written order and the "performed on the
 *    parent" shape the source application has always had.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    // Positional: Delta(0) [ Charlie(0), Bravo(1) ], Alpha(1)
    // Alphabetical would be the reverse at every level (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->delta->id, 'position' => 1]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

afterEach(function () {
    CategoryTreePage::$moveAuthorization = 'allow';
});

// ── A same-parent placement is a REORDER ─────────────────────────────────────

it('fires SiblingsReordered for a same-parent placement, not NodeMoved', function () {
    Event::fake([NodeMoved::class, SiblingsReordered::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    Event::assertDispatchedTimes(SiblingsReordered::class, 1);
    Event::assertNotDispatched(NodeMoved::class);
});

it('carries the complete written order on the event, not the rows the actor saw', function () {
    // ⚠️ The whole reason a host wants this event: "these, in this order". An
    // `ordered_ids` list that omitted the rows privacy hides would be a lie about
    // what was written, and the host would replay it as one.
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
    ]);

    Event::fake([SiblingsReordered::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    Event::assertDispatched(SiblingsReordered::class, function (SiblingsReordered $event) use ($hidden): bool {
        return $event->orderedKeys === [$hidden->id, $this->bravo->id, $this->charlie->id]
            && (int) $event->parentId === $this->delta->id;
    });
});

it('writes exactly the order it wrote before the routing changed', function () {
    // ⚠️ PA-3 changes which ACTION runs, and must not change the RESULT. The
    // expected order here is NOT [Bravo, Aardvark, Charlie]: the complete group
    // reads Aardvark(0), Charlie(0), Bravo(1) — Aardvark shares Charlie's position
    // and wins the name tie-break — so "before Charlie" is index 1 of the COMPLETE
    // group, and Bravo lands after the row the actor was never shown.
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
    ]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $this->charlie->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id],
    );

    expect(Stored::order($this->delta->id))
        ->toBe([$hidden->id, $this->bravo->id, $this->charlie->id]);
});

it('leaves the group contiguous and collision-free after a reorder', function () {
    // AGENTS.md R-009. `ReorderSiblings` writes 0..n-1; a routing change that
    // silently stopped renumbering would show up here and nowhere else.
    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->charlie->id,
        $this->delta->id,
        $this->bravo->id,
        SiblingPlacement::After->name,
        [$this->charlie->id, $this->bravo->id],
    );

    $positions = Category::query()
        ->where('parent_id', $this->delta->id)
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe(range(0, count($positions) - 1));
});

// ── A ROOT-level reorder — the case `$model` was added for ───────────────────

it('reorders a root-level group, which is what ReorderSiblings $model exists for', function () {
    // ⚠️ Root keys carry no model class and there is no parent to infer one from,
    // so `ReorderSiblings` throws unless the caller names the model. The bridge is
    // the caller that has to name it.
    Event::fake([SiblingsReordered::class, NodeMoved::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->alpha->id,
        null,
        $this->delta->id,
        SiblingPlacement::Before->name,
        [$this->delta->id, $this->alpha->id],
    );

    Event::assertDispatchedTimes(SiblingsReordered::class, 1);
    Event::assertDispatched(SiblingsReordered::class, fn (SiblingsReordered $e): bool => $e->parentId === null);

    expect(Stored::order(null))->toBe([$this->alpha->id, $this->delta->id]);
});

// ── A RE-PARENT is still a move ──────────────────────────────────────────────

it('still fires NodeMoved for a re-parent, and no reorder event', function () {
    // The two events describe different things. A host listening for one and
    // getting both would double-count.
    Event::fake([NodeMoved::class, SiblingsReordered::class]);

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

    Event::assertDispatchedTimes(NodeMoved::class, 1);
    Event::assertNotDispatched(SiblingsReordered::class);

    expect($this->alpha->fresh()->parent_id)->toBe($this->delta->id);
});

it('decides from the stored parent, not from the group the render filed the node under', function () {
    // ⚠️ An ORPHAN displays at the root while still belonging to its real group.
    // Routing from the display would reorder the wrong group entirely — so this
    // asserts the node whose parent is hidden is still treated as a member of its
    // real group, and moving it to the ROOT is therefore a re-parent.
    $hiddenParent = Category::create(['name' => 'Zulu', 'position' => 5, 'is_visible' => false]);
    $orphan = Category::create(['name' => 'Echo', 'parent_id' => $hiddenParent->id, 'position' => 0]);

    Event::fake([NodeMoved::class, SiblingsReordered::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $orphan->id,
        null,
        $this->delta->id,
        SiblingPlacement::After->name,
        [$this->delta->id, $this->alpha->id, $orphan->id],
    );

    // It is displayed at the root, but its stored parent is Zulu — so this is a
    // genuine re-parent, and a move.
    Event::assertDispatchedTimes(NodeMoved::class, 1);
    Event::assertNotDispatched(SiblingsReordered::class);

    expect($orphan->fresh()->parent_id)->toBeNull();
});

// ── PA-3 must not WIDEN what is permitted ────────────────────────────────────

it('still refuses a reorder inside a parent the host says cannot receive children', function () {
    // ⚠️ `MoveNode` asks `isValidTreeTarget()` and refuses, so before PA-3 a
    // reorder inside an inactive parent was refused. Routing reorders around
    // `MoveNode` would have started ALLOWING them — a silent widening of what is
    // permitted that nobody asked for. PA-3 changes which action runs, not which
    // moves are legal.
    $frozen = Category::create(['name' => 'Foxtrot', 'position' => 3, 'is_active' => false]);
    $one = Category::create(['name' => 'Golf', 'parent_id' => $frozen->id, 'position' => 0]);
    $two = Category::create(['name' => 'Hotel', 'parent_id' => $frozen->id, 'position' => 1]);

    $before = Stored::order($frozen->id);

    Event::fake([NodeMoved::class, SiblingsReordered::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $two->id,
        $frozen->id,
        $one->id,
        SiblingPlacement::Before->name,
        [$one->id, $two->id],
    );

    expect(Stored::order($frozen->id))->toBe($before);
    Event::assertNotDispatched(SiblingsReordered::class);
    Event::assertNotDispatched(NodeMoved::class);
});

it('refuses an unreachable reference on the reorder path too', function () {
    // The refusal vocabulary must not depend on which action the placement routed
    // through — a reorder naming a neighbour the actor never saw is still refused.
    $hidden = Category::create([
        'name' => 'Aardvark',
        'parent_id' => $this->delta->id,
        'position' => 0,
        'is_visible' => false,
    ]);

    $before = Stored::order($this->delta->id);

    Event::fake([SiblingsReordered::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->bravo->id,
        $this->delta->id,
        $hidden->id,
        SiblingPlacement::Before->name,
        [$this->charlie->id, $this->bravo->id, $hidden->id],
    );

    expect(Stored::order($this->delta->id))->toBe($before);
    Event::assertNotDispatched(SiblingsReordered::class);
});

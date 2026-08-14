<?php

declare(strict_types=1);

use Rolland\Tree\Actions\MoveNode;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Actions\ResolveSiblingPlacement;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Stored;

/**
 * ⚠️ Every assertion in this file is its OWN `it()` case, and NONE of them is
 * appended to an ordering assertion with `->and()`.
 *
 * A chain stops at the first failure, so a contiguity assertion hung off an
 * ordering one could never be watched failing on its own — and a guard whose red
 * has never been seen is unverified (quickstart.md § SC-002/SC-003, AGENTS.md
 * R-023). This is a lesson from the source application applied rather than
 * re-learned.
 */
beforeEach(function () {
    $this->place = new PlaceNode(new ResolveSiblingPlacement, new MoveNode);

    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);

    // Deliberately NOT dense and NOT contiguous — the state real data is in
    // before this package has ever touched it. Positional order reverses
    // alphabetical order so the two cannot be confused.
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->parent->id, 'position' => 4]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->parent->id, 'position' => 4]);
    $this->alpha = Category::create(['name' => 'Alpha', 'parent_id' => $this->parent->id, 'position' => 9]);

    $this->rendered = [$this->delta->id, $this->bravo->id, $this->charlie->id, $this->alpha->id];
});

it('leaves the affected group ordered as the caller asked', function () {
    $this->place->handle(
        $this->alpha,
        $this->parent,
        $this->delta,
        SiblingPlacement::After,
        $this->rendered,
    );

    // Read order before the move: Delta(0), Bravo(4), Charlie(4), Alpha(9)
    //   — Bravo before Charlie because the positions COLLIDE and the read
    //     tie-breaks by name. This is the state the tie-breaker exists for.
    expect(Stored::order($this->parent->id))
        ->toBe([$this->delta->id, $this->alpha->id, $this->bravo->id, $this->charlie->id]);
});

it('leaves the affected group holding a contiguous, collision-free sequence', function () {
    $this->place->handle(
        $this->alpha,
        $this->parent,
        $this->delta,
        SiblingPlacement::After,
        $this->rendered,
    );

    expect(Stored::positions($this->parent->id))->toBe([0, 1, 2, 3]);
});

it('starts the sequence at zero', function () {
    $this->place->handle(
        $this->alpha,
        $this->parent,
        $this->delta,
        SiblingPlacement::Before,
        $this->rendered,
    );

    expect(Stored::positions($this->parent->id)[0])->toBe(0);
});

it('holds a contiguous sequence in the group a node LEFT, not only the one it joined', function () {
    $destination = Category::create(['name' => 'Elsewhere', 'position' => 1]);

    $this->place->handle(
        $this->charlie,
        $destination,
        null,
        SiblingPlacement::LastChild,
        [],
    );

    expect(Stored::positions($this->parent->id))->toBe([0, 1, 2]);
});

it('holds a contiguous sequence among roots when a node is moved to the top level', function () {
    // Roots start as Root(0) and Elsewhere(1); Alpha joins them.
    $elsewhere = Category::create(['name' => 'Elsewhere', 'position' => 1]);

    $this->place->handle(
        $this->alpha,
        null,
        $this->parent,
        SiblingPlacement::After,
        [$this->parent->id, $elsewhere->id],
    );

    expect(Stored::positions(null))->toBe([0, 1, 2]);
});

it('rewrites a whole group to the given order when siblings are reordered directly', function () {
    (new ReorderSiblings)->handle($this->parent, [
        $this->alpha->id,
        $this->bravo->id,
        $this->charlie->id,
        $this->delta->id,
    ]);

    expect(Stored::order($this->parent->id))
        ->toBe([$this->alpha->id, $this->bravo->id, $this->charlie->id, $this->delta->id]);
});

it('leaves a directly-reordered group contiguous and collision-free', function () {
    (new ReorderSiblings)->handle($this->parent, [
        $this->alpha->id,
        $this->bravo->id,
        $this->charlie->id,
        $this->delta->id,
    ]);

    expect(Stored::positions($this->parent->id))->toBe([0, 1, 2, 3]);
});

it('does not renumber a group it did not touch', function () {
    // ⚠️ data-model.md is explicit that the package guarantees contiguity only for
    // groups it touched. A move that silently "tidied" the whole table would be
    // doing unasked-for writes to other people's rows — and would hide exactly the
    // legacy collisions the tie-breaker exists to survive.
    $other = Category::create(['name' => 'Other', 'position' => 1]);
    Category::create(['name' => 'Zulu', 'parent_id' => $other->id, 'position' => 7]);
    Category::create(['name' => 'Yankee', 'parent_id' => $other->id, 'position' => 7]);

    $this->place->handle(
        $this->alpha,
        $this->parent,
        $this->delta,
        SiblingPlacement::After,
        $this->rendered,
    );

    expect(Stored::positions($other->id))->toBe([7, 7]);
});

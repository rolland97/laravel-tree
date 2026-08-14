<?php

declare(strict_types=1);

use Rolland\Tree\Actions\MoveNode;
use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Stored;

beforeEach(function () {
    $this->move = new MoveNode;
});

// ── T028 — the cycle guard ───────────────────────────────────────────────────

it('refuses to move a node inside its own child', function () {
    $root = Category::create(['name' => 'Delta', 'position' => 0]);
    $child = Category::create(['name' => 'Charlie', 'parent_id' => $root->id, 'position' => 0]);

    expect(fn () => $this->move->handle($root, $child, 0))
        ->toThrow(CycleException::class);
});

it('refuses to move a node inside a deeper descendant', function () {
    // The case a naive "is the destination my direct child?" guard misses.
    $root = Category::create(['name' => 'Delta', 'position' => 0]);
    $child = Category::create(['name' => 'Charlie', 'parent_id' => $root->id, 'position' => 0]);
    $grandchild = Category::create(['name' => 'Bravo', 'parent_id' => $child->id, 'position' => 0]);

    expect(fn () => $this->move->handle($root, $grandchild, 0))
        ->toThrow(CycleException::class);
});

it('refuses to move a node inside itself', function () {
    $node = Category::create(['name' => 'Delta', 'position' => 0]);

    expect(fn () => $this->move->handle($node, $node, 0))
        ->toThrow(CycleException::class);
});

it('allows a move to an unrelated branch that merely looks similar', function () {
    // The guard must not be so broad it refuses legitimate moves — a guard that
    // refuses everything passes every refusal test and is still wrong.
    $left = Category::create(['name' => 'Delta', 'position' => 0]);
    $leftChild = Category::create(['name' => 'Charlie', 'parent_id' => $left->id, 'position' => 0]);
    $right = Category::create(['name' => 'Bravo', 'position' => 1]);

    $this->move->handle($leftChild, $right, 0);

    expect(Stored::order($right->id))->toBe([$leftChild->id]);
});

// ── T029 — the host's own validity rule ──────────────────────────────────────

it('refuses a destination the host says cannot receive children', function () {
    // ⚠️ The package has no opinion about what makes a target valid. This asserts
    // it ASKS, and obeys the answer (AGENTS.md R-003).
    $node = Category::create(['name' => 'Delta', 'position' => 0]);
    $inactive = Category::create(['name' => 'Charlie', 'position' => 1, 'is_active' => false]);

    expect($inactive->isValidTreeTarget())->toBeFalse();

    expect(fn () => $this->move->handle($node, $inactive, 0))
        ->toThrow(InvalidTargetException::class);
});

it('does not ask the host about validity when moving to the root', function () {
    // There is no node to ask. A guard that demanded one would make it impossible
    // to move anything back out to the top level.
    $parent = Category::create(['name' => 'Delta', 'position' => 0, 'is_active' => false]);
    $child = Category::create(['name' => 'Charlie', 'parent_id' => $parent->id, 'position' => 0]);

    $this->move->handle($child, null, 0);

    expect($child->fresh()->parent_id)->toBeNull();
});

it('leaves the tree untouched when it refuses', function () {
    $root = Category::create(['name' => 'Delta', 'position' => 0]);
    $child = Category::create(['name' => 'Charlie', 'parent_id' => $root->id, 'position' => 0]);
    $sibling = Category::create(['name' => 'Bravo', 'parent_id' => $root->id, 'position' => 1]);

    $before = Stored::order($root->id);

    try {
        $this->move->handle($root, $child, 0);
    } catch (CycleException) {
        // expected
    }

    expect(Stored::order($root->id))->toBe($before);
    expect($sibling->fresh()->position)->toBe(1);
});

// ── the move itself ──────────────────────────────────────────────────────────

it('inserts the node at the given index and pushes the rest down', function () {
    $parent = Category::create(['name' => 'Root', 'position' => 0]);

    // Positional order deliberately the reverse of alphabetical.
    $delta = Category::create(['name' => 'Delta', 'parent_id' => $parent->id, 'position' => 0]);
    $charlie = Category::create(['name' => 'Charlie', 'parent_id' => $parent->id, 'position' => 1]);
    $bravo = Category::create(['name' => 'Bravo', 'parent_id' => $parent->id, 'position' => 2]);
    $mover = Category::create(['name' => 'Mover', 'position' => 1]);

    $this->move->handle($mover, $parent, 1);

    expect(Stored::order($parent->id))->toBe([$delta->id, $mover->id, $charlie->id, $bravo->id]);
});

it('takes the whole subtree with it', function () {
    $origin = Category::create(['name' => 'Delta', 'position' => 0]);
    $destination = Category::create(['name' => 'Charlie', 'position' => 1]);
    $node = Category::create(['name' => 'Bravo', 'parent_id' => $origin->id, 'position' => 0]);
    $child = Category::create(['name' => 'Alpha', 'parent_id' => $node->id, 'position' => 0]);

    $this->move->handle($node, $destination, 0);

    expect($node->fresh()->parent_id)->toBe($destination->id);
    expect($child->fresh()->parent_id)->toBe($node->id);
});

it('renumbers the group the node left behind', function () {
    // The half of a cross-parent move that is easy to forget: the ORIGIN group is
    // now missing an index, and left alone it holds a gap for ever.
    $origin = Category::create(['name' => 'Root', 'position' => 0]);
    $destination = Category::create(['name' => 'Elsewhere', 'position' => 1]);

    $delta = Category::create(['name' => 'Delta', 'parent_id' => $origin->id, 'position' => 0]);
    $charlie = Category::create(['name' => 'Charlie', 'parent_id' => $origin->id, 'position' => 1]);
    $bravo = Category::create(['name' => 'Bravo', 'parent_id' => $origin->id, 'position' => 2]);

    $this->move->handle($charlie, $destination, 0);

    expect(Stored::positions($origin->id))->toBe([0, 1]);
    expect(Stored::order($origin->id))->toBe([$delta->id, $bravo->id]);
});

// ── T031 — atomicity ─────────────────────────────────────────────────────────

it('leaves the previous order intact when a write fails part-way through', function () {
    // ⚠️ AGENTS.md R-009: the move plus its renumbering MUST be atomic. Without a
    // transaction, a failure here leaves some siblings renumbered and the rest
    // holding stale, colliding positions — which is the exact shape of the 071
    // defect, arrived at by a different route.
    $parent = Category::create(['name' => 'Root', 'position' => 0]);

    $delta = Category::create(['name' => 'Delta', 'parent_id' => $parent->id, 'position' => 0]);
    $charlie = Category::create(['name' => 'Charlie', 'parent_id' => $parent->id, 'position' => 1]);
    $bravo = Category::create(['name' => 'Bravo', 'parent_id' => $parent->id, 'position' => 2]);
    $alpha = Category::create(['name' => 'Alpha', 'parent_id' => $parent->id, 'position' => 3]);

    $before = Stored::order($parent->id);
    $beforePositions = Stored::positions($parent->id);

    // Explode on the third row the renumber touches, so at least one write has
    // already landed by the time it blows up.
    $seen = 0;
    Category::saving(function () use (&$seen): void {
        $seen++;
        if ($seen === 3) {
            throw new RuntimeException('write failed part-way through');
        }
    });

    try {
        $this->move->handle($alpha, $parent, 0);
    } catch (RuntimeException) {
        // expected
    } finally {
        Category::flushEventListeners();
    }

    expect($seen)->toBeGreaterThanOrEqual(3, 'the failure must occur AFTER a write has landed, or this proves nothing');
    expect(Stored::order($parent->id))->toBe($before);
    expect(Stored::positions($parent->id))->toBe($beforePositions);
});

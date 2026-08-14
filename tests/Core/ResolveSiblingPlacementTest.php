<?php

declare(strict_types=1);

use Rolland\Tree\Actions\ResolveSiblingPlacement;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Exceptions\UnreachableReferenceException;
use Rolland\Tree\Tests\Fixtures\Category;

/**
 * ⚠️ Fixture naming is a CORRECTNESS concern in this file, not cosmetics.
 *
 * The read order tie-breaks by name (research R3), so a carelessly-named fixture
 * makes a right and a wrong implementation produce the SAME visible order. Two of
 * fourteen guards in the source application passed against the live defect for
 * exactly this reason (AGENTS.md R-025, spec FR-048).
 *
 * Every group below is therefore built so that alphabetical order and positional
 * order DISAGREE.
 */
beforeEach(function () {
    $this->resolve = new ResolveSiblingPlacement;

    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);

    // Positional order: Delta, Charlie, Bravo, Alpha
    // Alphabetical:     Alpha, Bravo, Charlie, Delta   ← deliberately the reverse
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->parent->id, 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->parent->id, 'position' => 2]);
    $this->alpha = Category::create(['name' => 'Alpha', 'parent_id' => $this->parent->id, 'position' => 3]);

    // The node being moved in, from somewhere else entirely.
    $this->mover = Category::create(['name' => 'Mover', 'position' => 1]);

    $this->allVisible = [
        $this->delta->id,
        $this->charlie->id,
        $this->bravo->id,
        $this->alpha->id,
    ];
});

// ── T022 — Before / After against a fully-visible group ──────────────────────

it('resolves Before to the index the reference currently occupies', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->bravo,          // third by position, SECOND by name
        SiblingPlacement::Before,
        $this->allVisible,
    );

    // Complete group in read order: Delta(0), Charlie(1), Bravo(2), Alpha(3).
    // An implementation ordering by NAME would answer 1 here, not 2.
    expect($index)->toBe(2);
});

it('resolves After to one past the reference', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->charlie,
        SiblingPlacement::After,
        $this->allVisible,
    );

    // Charlie is second by position, third by name. Positional answer: 2.
    expect($index)->toBe(2);
});

it('resolves Before the first sibling to zero', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->delta,
        SiblingPlacement::Before,
        $this->allVisible,
    );

    expect($index)->toBe(0);
});

it('resolves After the last sibling to the end of the complete group', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->alpha,
        SiblingPlacement::After,
        $this->allVisible,
    );

    expect($index)->toBe(4);
});

it('excludes the node being moved from the group it resolves against', function () {
    // Moving Charlie down within its own group. The complete group EXCLUDING
    // Charlie is Delta(0), Bravo(1), Alpha(2) — so "after Bravo" is index 2.
    // An implementation that left Charlie in the list would answer 3.
    $index = $this->resolve->handle(
        $this->charlie,
        $this->parent,
        $this->bravo,
        SiblingPlacement::After,
        $this->allVisible,
    );

    expect($index)->toBe(2);
});

// ── T023 — the partial-visibility case (SC-002) ──────────────────────────────

it('resolves against the complete group when a hidden sibling sits between two visible ones', function () {
    // ⚠️ `Aardvark` is not an arbitrary name. It sorts FIRST alphabetically while
    // sitting SECOND by position, so a broken implementation that tie-breaks its
    // way to an answer cannot land on the same index as a correct one
    // (quickstart.md § SC-002).
    $group = Category::create(['name' => 'Group', 'position' => 2]);

    $alpha = Category::create(['name' => 'Alpha', 'parent_id' => $group->id, 'position' => 0]);
    $aardvark = Category::create(['name' => 'Aardvark', 'parent_id' => $group->id, 'position' => 1]);
    $charlie = Category::create(['name' => 'Charlie', 'parent_id' => $group->id, 'position' => 2]);

    $index = $this->resolve->handle(
        $this->mover,
        $group,
        $alpha,
        SiblingPlacement::After,
        [$alpha->id, $charlie->id],   // the actor never saw Aardvark
    );

    // Complete group: Alpha(0), Aardvark(1), Charlie(2). After Alpha => 1.
    // The moved node lands between Alpha and AARDVARK, not between Alpha and Charlie.
    expect($index)->toBe(1);
});

it('resolves against the complete group when a hidden sibling sits before the reference', function () {
    // The case where resolving in RENDERED space gives a different number, and so
    // the one that actually catches the 071 defect.
    //
    // Positional: Aardvark(0), Delta(1), Bravo(2)   Alphabetical: Aardvark, Bravo, Delta
    $group = Category::create(['name' => 'Group', 'position' => 2]);

    $aardvark = Category::create(['name' => 'Aardvark', 'parent_id' => $group->id, 'position' => 0]);
    $delta = Category::create(['name' => 'Delta', 'parent_id' => $group->id, 'position' => 1]);
    $bravo = Category::create(['name' => 'Bravo', 'parent_id' => $group->id, 'position' => 2]);

    $index = $this->resolve->handle(
        $this->mover,
        $group,
        $delta,
        SiblingPlacement::After,
        [$delta->id, $bravo->id],     // Aardvark hidden
    );

    // Complete: Aardvark(0), Delta(1), Bravo(2). After Delta => 2.
    // Resolving in rendered space would answer 1, and silently place the node
    // above a row the actor was never shown.
    expect($index)->toBe(2);
});

// ── T024/T025/T026 — the three refusals that share one type ──────────────────

it('refuses a reference that is not a member of the complete destination group', function () {
    $elsewhere = Category::create(['name' => 'Elsewhere', 'position' => 9]);

    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->parent,
        $elsewhere,
        SiblingPlacement::After,
        $this->allVisible,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a reference the actor was never shown, even though it is in the group', function () {
    // ⚠️ This is the authorization half of Principle II. The reference IS a real
    // sibling; the actor simply could not have aimed at it. Resolving it anyway
    // lets a tampered payload address a node privacy hides.
    $rendered = [$this->delta->id, $this->charlie->id, $this->alpha->id];   // Bravo omitted

    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->bravo,
        SiblingPlacement::After,
        $rendered,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a reference that is the node being moved', function () {
    expect(fn () => $this->resolve->handle(
        $this->charlie,
        $this->parent,
        $this->charlie,
        SiblingPlacement::After,
        $this->allVisible,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a placement that needs a reference when none is supplied', function () {
    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->parent,
        null,
        SiblingPlacement::Before,
        $this->allVisible,
    ))->toThrow(UnreachableReferenceException::class);
});

// ── T027 — LastChild names no neighbour ──────────────────────────────────────

it('resolves LastChild with no reference at all', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        null,
        SiblingPlacement::LastChild,
        $this->allVisible,
    );

    expect($index)->toBe(4);
});

it('resolves LastChild among roots when the destination parent is null', function () {
    // Roots at this point: Root(0) and Mover(1). Excluding Mover leaves one.
    $index = $this->resolve->handle(
        $this->mover,
        null,
        null,
        SiblingPlacement::LastChild,
        [$this->parent->id],
    );

    expect($index)->toBe(1);
});

it('resolves LastChild into an empty destination to zero', function () {
    $empty = Category::create(['name' => 'Empty', 'position' => 3]);

    $index = $this->resolve->handle($this->mover, $empty, null, SiblingPlacement::LastChild, []);

    expect($index)->toBe(0);
});

it('ignores a reference supplied alongside LastChild rather than refusing it', function () {
    // LastChild names no neighbour. A caller that passes one is not aiming at it,
    // and refusing would make the enum's own `needsReference()` a lie.
    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->delta,
        SiblingPlacement::LastChild,
        $this->allVisible,
    );

    expect($index)->toBe(4);
});

// ── T033 — numeric-string keys (research R4) ─────────────────────────────────

it('matches a reference whose key arrives from the driver as a numeric string', function () {
    // ⚠️ Found by static analysis in the source application, not by a test.
    // `pluck()->all()` is typed array<mixed>; a key arriving as "3" would never
    // match integer 3 under a strict comparison, and the move would be refused as
    // "reference not in group" for a perfectly valid reference (research R4).
    $renderedAsStrings = array_map(strval(...), $this->allVisible);

    $index = $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->bravo,
        SiblingPlacement::Before,
        $renderedAsStrings,
    );

    expect($index)->toBe(2);
});

it('still refuses an unseen reference when the rendered ids are numeric strings', function () {
    // The mirror of the test above. Loosening the comparison to fix R4 must not
    // loosen it far enough to let the visibility refusal through.
    $rendered = array_map(strval(...), [$this->delta->id, $this->charlie->id, $this->alpha->id]);

    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->parent,
        $this->bravo,
        SiblingPlacement::After,
        $rendered,
    ))->toThrow(UnreachableReferenceException::class);
});

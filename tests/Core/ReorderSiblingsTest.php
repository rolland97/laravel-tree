<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Page;
use Rolland\Tree\Tests\Stored;

/**
 * I3 — `ReorderSiblings` could not address a ROOT-level group.
 *
 * ⚠️ The keys it takes are bare scalars carrying no model class, and the model
 * was resolved from `$parent`. A root group has no parent, so the action threw —
 * a public entry point that was unusable for a whole class of groups.
 *
 * The contract is amended rather than worked around: a trailing optional
 * `$model` names the class when there is no parent to infer it from.
 * ⚠️ TRAILING and OPTIONAL on purpose. AGENTS.md R-030 requires a RENAME when a
 * signature's MEANING changes, because a stale positional call would otherwise
 * stay valid and mean something else. Appending a parameter changes no existing
 * position, so every existing call keeps its exact meaning and no rename is owed.
 */
beforeEach(function () {
    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);

    // Positional order deliberately the reverse of alphabetical (AGENTS.md R-025).
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->parent->id, 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->parent->id, 'position' => 2]);
});

it('reorders a root-level group when told which model to read', function () {
    $zulu = Category::create(['name' => 'Zulu', 'position' => 1]);
    $alpha = Category::create(['name' => 'Alpha', 'position' => 2]);

    (new ReorderSiblings)->handle(null, [$alpha->id, $zulu->id, $this->parent->id], Category::class);

    expect(Stored::order(null))->toBe([$alpha->id, $zulu->id, $this->parent->id]);
});

it('leaves a reordered root group contiguous and collision-free', function () {
    // Its own case, never chained to the ordering assertion above.
    $zulu = Category::create(['name' => 'Zulu', 'position' => 1]);

    (new ReorderSiblings)->handle(null, [$zulu->id, $this->parent->id], Category::class);

    expect(Stored::positions(null))->toBe([0, 1]);
});

it('fires SiblingsReordered exactly once for a root-level reorder', function () {
    Event::fake([SiblingsReordered::class]);

    $zulu = Category::create(['name' => 'Zulu', 'position' => 1]);

    (new ReorderSiblings)->handle(null, [$zulu->id, $this->parent->id], Category::class);

    Event::assertDispatchedTimes(SiblingsReordered::class, 1);
});

it('reports a null parent on the root-level event', function () {
    Event::fake([SiblingsReordered::class]);

    $zulu = Category::create(['name' => 'Zulu', 'position' => 1]);

    (new ReorderSiblings)->handle(null, [$zulu->id, $this->parent->id], Category::class);

    Event::assertDispatched(
        SiblingsReordered::class,
        fn (SiblingsReordered $event): bool => $event->parentId === null
    );
});

it('still infers the model from the parent when there is one', function () {
    // The existing call shape keeps working, unchanged and un-renamed.
    (new ReorderSiblings)->handle($this->parent, [
        $this->bravo->id, $this->charlie->id, $this->delta->id,
    ]);

    expect(Stored::order($this->parent->id))
        ->toBe([$this->bravo->id, $this->charlie->id, $this->delta->id]);
});

it('refuses a root-level reorder that names no model, rather than guessing one', function () {
    // ⚠️ There is nothing to infer from, and guessing would be worse than
    // refusing: it would silently reorder some other table's roots.
    expect(fn () => (new ReorderSiblings)->handle(null, [$this->parent->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a model that is not a tree node', function () {
    expect(fn () => (new ReorderSiblings)->handle(null, [1], stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('reorders a root-level group on the second model too', function () {
    // FR-045: one consumer proves nothing.
    $this->usePageColumns();

    $zulu = Page::create(['title' => 'Zulu', 'sort_order' => 0]);
    $alpha = Page::create(['title' => 'Alpha', 'sort_order' => 1]);

    (new ReorderSiblings)->handle(null, [$alpha->id, $zulu->id], Page::class);

    expect(Stored::pageOrder(null))->toBe([$alpha->id, $zulu->id]);
});

it('does not touch a group it was not given', function () {
    $zulu = Category::create(['name' => 'Zulu', 'position' => 1]);

    (new ReorderSiblings)->handle(null, [$zulu->id, $this->parent->id], Category::class);

    // The children of $parent are a different group and must be untouched.
    expect(Stored::order($this->parent->id))
        ->toBe([$this->delta->id, $this->charlie->id, $this->bravo->id]);
});

// ── F22 — the documented `list` was a promise the code did not keep ──────────

it('writes contiguous positions when the caller passes a KEYED array', function () {
    // ⚠️ `$orderedKeys` is documented `list<int|string>`, and the loop that writes
    // positions used the array KEY as the position. `array_map` preserves keys, so
    // nothing enforced that promise — and a caller who filtered a list before
    // passing it got its ORIGINAL indexes written as positions.
    //
    // `array_filter` preserving keys is the obvious real path to this, which makes
    // it a plausible host mistake rather than a theoretical one. The package must
    // not write a non-contiguous group (AGENTS.md R-009) because a caller handed it
    // gappy keys.
    // The gappy keys a real `array_filter` leaves behind.
    $keyed = [3 => $this->bravo->id, 7 => $this->delta->id, 9 => $this->charlie->id];

    (new ReorderSiblings)->handle($this->parent, $keyed);

    expect(Stored::order($this->parent->id))
        ->toBe([$this->bravo->id, $this->delta->id, $this->charlie->id]);

    expect(Stored::positions($this->parent->id))->toBe([0, 1, 2]);
});

it('emits a real list on SiblingsReordered even from a keyed call', function () {
    // ⚠️ A keyed array serialises to a JSON OBJECT, so a host storing
    // `orderedKeys` in an audit trail gets `{"3":12}` where it expected `[12]` —
    // and every consumer that indexes it numerically breaks. The event's own
    // docblock says `list`; this is what makes that true.
    Event::fake([SiblingsReordered::class]);

    (new ReorderSiblings)->handle($this->parent, [5 => $this->charlie->id, 2 => $this->bravo->id]);

    Event::assertDispatched(SiblingsReordered::class, function (SiblingsReordered $event): bool {
        return array_is_list($event->orderedKeys)
            && $event->orderedKeys === [$this->charlie->id, $this->bravo->id];
    });
});

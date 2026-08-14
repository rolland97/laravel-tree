<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Rolland\Tree\Actions\MoveNode;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Actions\ResolveSiblingPlacement;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Exceptions\UnreachableReferenceException;
use Rolland\Tree\Tests\Fixtures\Page;
use Rolland\Tree\Tests\Stored;

/**
 * T034 / spec FR-045 / SC-010 — every core behaviour, proved a second time against
 * a model that shares nothing with the first.
 *
 * ⚠️ Research R9: one consumer proves nothing. Every configurable seam — column
 * names, tie-breaker, validity predicate — can be accidentally hard-coded to the
 * shape of a single fixture and still pass. `Page` has no `parent_id`, no
 * `position` and no `name` column at all, so anything hard-coded to `Category`
 * fails loudly here instead of quietly ordering by something else.
 */
beforeEach(function () {
    $this->usePageColumns();

    $this->resolve = new ResolveSiblingPlacement;
    $this->place = app(PlaceNode::class);

    $this->section = Page::create(['title' => 'Root', 'sort_order' => 0]);

    // Positional order deliberately the reverse of alphabetical.
    $this->delta = Page::create(['title' => 'Delta', 'section_id' => $this->section->id, 'sort_order' => 0]);
    $this->charlie = Page::create(['title' => 'Charlie', 'section_id' => $this->section->id, 'sort_order' => 1]);
    $this->bravo = Page::create(['title' => 'Bravo', 'section_id' => $this->section->id, 'sort_order' => 2]);
    $this->alpha = Page::create(['title' => 'Alpha', 'section_id' => $this->section->id, 'sort_order' => 3]);

    $this->rendered = [$this->delta->id, $this->charlie->id, $this->bravo->id, $this->alpha->id];
    $this->mover = Page::create(['title' => 'Mover', 'sort_order' => 1]);
});

it('resolves Before against a model with entirely different column names', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->section,
        $this->bravo,
        SiblingPlacement::Before,
        $this->rendered,
    );

    expect($index)->toBe(2);
});

it('resolves After against a model with entirely different column names', function () {
    $index = $this->resolve->handle(
        $this->mover,
        $this->section,
        $this->charlie,
        SiblingPlacement::After,
        $this->rendered,
    );

    expect($index)->toBe(2);
});

it('resolves LastChild with no reference against the second model', function () {
    $index = $this->resolve->handle($this->mover, $this->section, null, SiblingPlacement::LastChild, $this->rendered);

    expect($index)->toBe(4);
});

it('orders the second model by its OWN tie-breaker, not by a hard-coded name column', function () {
    // ⚠️ Page has no `name` column. A tie-breaker hard-coded to `name` produces an
    // SQL error here rather than a wrong answer — which is the failure mode worth
    // having, and the reason this fixture has no such column.
    $group = Page::create(['title' => 'Group', 'sort_order' => 2]);

    // Colliding positions, so the tie-breaker is the ONLY thing deciding the order.
    $zulu = Page::create(['title' => 'Zulu', 'section_id' => $group->id, 'sort_order' => 3]);
    $alpha = Page::create(['title' => 'Alpha', 'section_id' => $group->id, 'sort_order' => 3]);

    $index = $this->resolve->handle($this->mover, $group, $zulu, SiblingPlacement::Before, [$alpha->id, $zulu->id]);

    // Read order: Alpha then Zulu (positions collide, `title` breaks the tie).
    expect($index)->toBe(1);
});

it('refuses an unseen reference on the second model', function () {
    $rendered = [$this->delta->id, $this->charlie->id, $this->alpha->id];   // Bravo omitted

    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->section,
        $this->bravo,
        SiblingPlacement::After,
        $rendered,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a reference outside the group on the second model', function () {
    $elsewhere = Page::create(['title' => 'Elsewhere', 'sort_order' => 9]);

    expect(fn () => $this->resolve->handle(
        $this->mover,
        $this->section,
        $elsewhere,
        SiblingPlacement::After,
        $this->rendered,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a self-reference on the second model', function () {
    expect(fn () => $this->resolve->handle(
        $this->charlie,
        $this->section,
        $this->charlie,
        SiblingPlacement::After,
        $this->rendered,
    ))->toThrow(UnreachableReferenceException::class);
});

it('refuses a cycle on the second model', function () {
    expect(fn () => (new MoveNode)->handle($this->section, $this->charlie, 0))
        ->toThrow(CycleException::class);
});

it('obeys the second model\'s own validity rule, which is a different column', function () {
    // Category answers `is_active`; Page answers `published`. The package asks
    // both the same question and has an opinion about neither.
    $unpublished = Page::create(['title' => 'Elsewhere', 'sort_order' => 9, 'published' => false]);

    expect(fn () => (new MoveNode)->handle($this->mover, $unpublished, 0))
        ->toThrow(InvalidTargetException::class);
});

it('places a node correctly on the second model', function () {
    $this->place->handle($this->mover, $this->section, $this->delta, SiblingPlacement::After, $this->rendered);

    expect(Stored::pageOrder($this->section->id))->toBe([
        $this->delta->id,
        $this->mover->id,
        $this->charlie->id,
        $this->bravo->id,
        $this->alpha->id,
    ]);
});

it('leaves the second model\'s affected group contiguous and collision-free', function () {
    $this->place->handle($this->mover, $this->section, $this->delta, SiblingPlacement::After, $this->rendered);

    expect(Stored::pagePositions($this->section->id))->toBe([0, 1, 2, 3, 4]);
});

it('fires NodeMoved exactly once on the second model', function () {
    Event::fake([NodeMoved::class]);

    $this->place->handle($this->mover, $this->section, $this->delta, SiblingPlacement::After, $this->rendered);

    Event::assertDispatchedTimes(NodeMoved::class, 1);
});

it('fires SiblingsReordered exactly once on the second model', function () {
    Event::fake([SiblingsReordered::class]);

    (new ReorderSiblings)->handle($this->section, [
        $this->alpha->id, $this->bravo->id, $this->charlie->id, $this->delta->id,
    ]);

    Event::assertDispatchedTimes(SiblingsReordered::class, 1);
});

it('matches numeric-string keys on the second model too', function () {
    $renderedAsStrings = array_map(strval(...), $this->rendered);

    $index = $this->resolve->handle(
        $this->mover,
        $this->section,
        $this->bravo,
        SiblingPlacement::Before,
        $renderedAsStrings,
    );

    expect($index)->toBe(2);
});

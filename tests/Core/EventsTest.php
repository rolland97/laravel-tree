<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Tests\Fixtures\Category;

beforeEach(function () {
    $this->place = app(PlaceNode::class);

    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->parent->id, 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->parent->id, 'position' => 2]);

    $this->rendered = [$this->delta->id, $this->charlie->id, $this->bravo->id];
});

// ── T032 ─────────────────────────────────────────────────────────────────────

it('fires NodeMoved exactly once for one move', function () {
    // ⚠️ AGENTS.md R-011 / spec FR-041. A completed interaction fires ONE event,
    // however many keystrokes or pointer events produced it. Firing per write —
    // or once per renumbered sibling — would write one audit row per keystroke for
    // what the user thinks of as a single move.
    Event::fake([NodeMoved::class, SiblingsReordered::class]);

    $this->place->handle($this->bravo, $this->parent, $this->delta, SiblingPlacement::Before, $this->rendered);

    Event::assertDispatchedTimes(NodeMoved::class, 1);
});

it('reports where the node came from and where it went', function () {
    Event::fake([NodeMoved::class]);

    $destination = Category::create(['name' => 'Elsewhere', 'position' => 1]);

    $this->place->handle($this->charlie, $destination, null, SiblingPlacement::LastChild, []);

    Event::assertDispatched(NodeMoved::class, function (NodeMoved $event): bool {
        return $event->node->getKey() === $this->charlie->id
            && (int) $event->previousParentId === $this->parent->id
            && $event->position === 0;
    });
});

it('reports a move to the top level as a null destination parent', function () {
    Event::fake([NodeMoved::class]);

    $this->place->handle($this->charlie, null, $this->parent, SiblingPlacement::After, [$this->parent->id]);

    Event::assertDispatched(NodeMoved::class, fn (NodeMoved $event): bool => $event->newParentId === null);
});

it('fires SiblingsReordered exactly once for one reorder', function () {
    Event::fake([SiblingsReordered::class, NodeMoved::class]);

    (new ReorderSiblings)->handle($this->parent, [$this->bravo->id, $this->delta->id, $this->charlie->id]);

    Event::assertDispatchedTimes(SiblingsReordered::class, 1);
});

it('does not fire a reorder event for a move', function () {
    // The two events describe different things. A host listening for one and
    // getting both would double-count.
    Event::fake([SiblingsReordered::class, NodeMoved::class]);

    $this->place->handle($this->bravo, $this->parent, $this->delta, SiblingPlacement::Before, $this->rendered);

    Event::assertNotDispatched(SiblingsReordered::class);
});

it('carries the complete written order on SiblingsReordered, not the visible subset', function () {
    Event::fake([SiblingsReordered::class]);

    (new ReorderSiblings)->handle($this->parent, [$this->bravo->id, $this->delta->id, $this->charlie->id]);

    Event::assertDispatched(SiblingsReordered::class, function (SiblingsReordered $event): bool {
        return $event->orderedKeys === [$this->bravo->id, $this->delta->id, $this->charlie->id]
            && (int) $event->parentId === $this->parent->id;
    });
});

it('fires no event at all when the move is refused', function () {
    Event::fake([NodeMoved::class, SiblingsReordered::class]);

    try {
        $this->place->handle($this->bravo, $this->parent, $this->parent, SiblingPlacement::After, $this->rendered);
    } catch (Throwable) {
        // expected — the parent is not a member of its own children's group
    }

    Event::assertNotDispatched(NodeMoved::class);
    Event::assertNotDispatched(SiblingsReordered::class);
});

// ── the package writes NO audit record ───────────────────────────────────────

it('writes no audit record of its own', function () {
    // ⚠️ Research R5 / AGENTS.md R-004. This is the single largest decoupling win
    // in the extraction: it removes spatie/laravel-activitylog from the dependency
    // set entirely. The host already has an audit trail if it wants one, and
    // already knows who its actor is; the package would have to guess both.
    $before = Category::count();

    $this->place->handle($this->bravo, $this->parent, $this->delta, SiblingPlacement::Before, $this->rendered);

    expect(Category::count())->toBe($before);

    // A string, not `Activity::class` — Pint's fully_qualified_strict_types fixer
    // turns the latter into a real `use Spatie\Activitylog\...` import of a class
    // that must never exist here, which reads as the opposite of what is meant.
    expect(class_exists('Spatie\Activitylog\Models\Activity'))->toBeFalse();
});

it('requires no audit, auth or logging package at runtime', function () {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    $forbidden = ['activitylog', 'permission', 'auth', 'filament'];

    foreach (array_keys($composer['require']) as $package) {
        foreach ($forbidden as $needle) {
            expect(str_contains($package, $needle))->toBeFalse(
                "runtime requirement `{$package}` matches forbidden `{$needle}` (AGENTS.md R-001, R-004)"
            );
        }
    }
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Stored;

/**
 * I2 — spec.md § Edge Cases: *"A group contains exactly one node. Every reorder
 * request is a no-op that must be reported, not silently accepted."*
 *
 * ⚠️ The keyboard already announced `only_child` while a held node was moved.
 * The POINTER did not: dragging the sole child of a parent back onto that parent
 * silently did nothing, and — worse — still wrote and still fired `NodeMoved`,
 * so a host's audit trail recorded a move that never happened.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;

    $this->parent = Category::create(['name' => 'Root', 'position' => 0]);
    $this->only = Category::create(['name' => 'Solo', 'parent_id' => $this->parent->id, 'position' => 0]);
});

it('reports that the node is an only child rather than accepting the request silently', function () {
    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->only->id,
            $this->parent->id,
            null,
            'LastChild',
            [$this->only->id],
        )
        ->assertDispatched('ltree-announce');
});

it('carries the host-overridable only-child wording in the report', function () {
    Livewire::test(CategoryTreePage::class)
        ->call(
            'placeNode',
            $this->only->id,
            $this->parent->id,
            null,
            'LastChild',
            [$this->only->id],
        )
        ->assertDispatched('ltree-announce', fn (string $event, array $params): bool => str_contains($params['message'] ?? '', 'only child')
        );
});

it('fires no move event for a request that cannot change anything', function () {
    // ⚠️ A no-op that still fired NodeMoved would write an audit row for a move
    // the user did not make — the exact opposite of what R-011 protects.
    Event::fake([NodeMoved::class]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->only->id,
        $this->parent->id,
        null,
        'LastChild',
        [$this->only->id],
    );

    Event::assertNotDispatched(NodeMoved::class);
});

it('leaves the stored rows untouched', function () {
    $before = Stored::order($this->parent->id);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $this->only->id,
        $this->parent->id,
        null,
        'LastChild',
        [$this->only->id],
    );

    expect(Stored::order($this->parent->id))->toBe($before);
});

it('still moves an only child to a DIFFERENT parent', function () {
    // ⚠️ The guard must not be so broad it refuses real work. Being an only child
    // makes a REORDER a no-op; it does not make a RE-PARENT one.
    $elsewhere = Category::create(['name' => 'Elsewhere', 'position' => 1]);

    // The fixture page asks to confirm cross-parent moves, so this is a two-step
    // interaction — which is itself the point: the only-child guard must let the
    // request reach the confirmation rather than swallowing it.
    Livewire::test(CategoryTreePage::class)
        ->call('placeNode', $this->only->id, $elsewhere->id, null, 'LastChild', [])
        ->assertSet('pendingMove.nodeKey', $this->only->id)
        ->call('confirmPendingMove');

    expect($this->only->fresh()->parent_id)->toBe($elsewhere->id);
});

it('still reorders a group that holds more than one node', function () {
    $second = Category::create(['name' => 'Alpha', 'parent_id' => $this->parent->id, 'position' => 1]);

    Livewire::test(CategoryTreePage::class)->call(
        'placeNode',
        $second->id,
        $this->parent->id,
        $this->only->id,
        'Before',
        [$this->only->id, $second->id],
    );

    expect(Stored::order($this->parent->id))->toBe([$second->id, $this->only->id]);
});

it('reports an only child at the root level too', function () {
    // The root group is a group like any other.
    Category::query()->delete();
    $lonely = Category::create(['name' => 'Lonely', 'position' => 0]);

    Livewire::test(CategoryTreePage::class)
        ->call('placeNode', $lonely->id, null, null, 'LastChild', [$lonely->id])
        ->assertDispatched('ltree-announce');
});

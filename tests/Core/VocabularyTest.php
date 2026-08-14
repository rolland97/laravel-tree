<?php

declare(strict_types=1);

use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Exceptions\UnreachableReferenceException;
use Rolland\Tree\Tests\Fixtures\Category;

it('knows which placements name a neighbour and which do not', function () {
    expect(SiblingPlacement::Before->needsReference())->toBeTrue();
    expect(SiblingPlacement::After->needsReference())->toBeTrue();

    // LastChild names no neighbour, which is why it was already correct in the
    // source application before the 071 defect — there was no index to get wrong.
    expect(SiblingPlacement::LastChild->needsReference())->toBeFalse();
});

it('offers exactly three placements', function () {
    expect(array_map(
        fn (SiblingPlacement $case): string => $case->name,
        SiblingPlacement::cases()
    ))->toBe(['Before', 'After', 'LastChild']);
});

// ⚠️ AGENTS.md R-010 / spec FR-013. A host reacts differently to each refusal, so
// they MUST be distinguishable by type rather than by parsing one generic message.
it('makes every refusal distinguishable by type', function () {
    expect(new CycleException)->toBeInstanceOf(DomainException::class);
    expect(new InvalidTargetException)->toBeInstanceOf(DomainException::class);
    expect(new UnreachableReferenceException)->toBeInstanceOf(DomainException::class);

    expect(new CycleException)->not->toBeInstanceOf(InvalidTargetException::class);
    expect(new CycleException)->not->toBeInstanceOf(UnreachableReferenceException::class);
    expect(new InvalidTargetException)->not->toBeInstanceOf(UnreachableReferenceException::class);
});

it('carries the previous and the new parent on NodeMoved, not just the new one', function () {
    // A listener recreating an audit entry needs to know where the node came FROM.
    // Carrying only the destination would force every host to have read the row
    // before the move, which the package gives them no hook to do.
    $node = new Category(['name' => 'Alpha']);

    $event = new NodeMoved(
        node: $node,
        previousParentId: 7,
        newParentId: null,
        position: 2,
    );

    expect($event->node)->toBe($node)
        ->and($event->previousParentId)->toBe(7)
        ->and($event->newParentId)->toBeNull()
        ->and($event->position)->toBe(2);
});

it('carries the written order on SiblingsReordered', function () {
    $event = new SiblingsReordered(parentId: null, orderedKeys: [3, 1, 2]);

    expect($event->parentId)->toBeNull()
        ->and($event->orderedKeys)->toBe([3, 1, 2]);
});

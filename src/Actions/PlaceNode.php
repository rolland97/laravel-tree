<?php

declare(strict_types=1);

namespace Rolland\Tree\Actions;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Exceptions\UnreachableReferenceException;

/**
 * The composed entry point: resolve, then move. **This is what hosts should call.**
 *
 * ⚠️ Named `PlaceNode`, never `DropNode`, and this naming is load-bearing.
 *
 * In the source application the equivalent Livewire method was renamed
 * `dropNode` → `placeNode` AS THE SAFETY MECHANISM: its parameters reordered, so a
 * stale positional call would have stayed syntactically valid and silently meant
 * something else. Named arguments do NOT catch that — Livewire dispatches through
 * the container's method injection, which matches by name and silently discards
 * unknown keys; a probe passing two arguments the method did not declare ran clean
 * on defaults. A *renamed* method throws before dispatch.
 *
 * If this signature ever changes again, RENAME IT AGAIN (`AGENTS.md` R-030).
 */
final class PlaceNode
{
    public function __construct(
        private readonly ResolveSiblingPlacement $resolve,
        private readonly MoveNode $move,
    ) {}

    /**
     * @param  list<int|string>  $renderedSiblingIds  ⚠️ NO DEFAULT, on purpose. An empty
     *                                                default would silently turn the visibility
     *                                                refusal into a no-op for every caller who
     *                                                omitted it. A caller that genuinely renders
     *                                                everything passes everything.
     *
     * @throws CycleException|InvalidTargetException|UnreachableReferenceException
     */
    public function handle(
        Model&TreeNode $node,
        ?TreeNode $destinationParent,
        ?TreeNode $reference,
        SiblingPlacement $placement,
        array $renderedSiblingIds,
    ): void {
        // Resolve FIRST: an unreachable reference must be refused before anything
        // is written, so a refusal leaves the tree exactly as it was.
        $position = $this->resolve->handle(
            $node,
            $destinationParent,
            $reference,
            $placement,
            $renderedSiblingIds,
        );

        $this->move->handle($node, $destinationParent, $position);
    }
}

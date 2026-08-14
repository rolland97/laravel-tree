<?php

declare(strict_types=1);

namespace Rolland\Tree\Events;

use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Contracts\TreeNode;

/**
 * A node was re-parented and/or re-positioned.
 *
 * ⚠️ Fired exactly ONCE per completed interaction, however many keystrokes or
 * pointer events produced it (AGENTS.md R-011, spec FR-041). A keyboard move that
 * fired per arrow press would write one audit row per keystroke for what the user
 * thinks of as one move.
 *
 * The package writes no audit record of its own. This event is how a host records
 * one — it already has an audit trail if it wants one, and already knows who its
 * actor is; the package would have to guess both (research R5).
 */
final class NodeMoved
{
    public function __construct(
        public readonly Model&TreeNode $node,
        public readonly int|string|null $previousParentId,
        public readonly int|string|null $newParentId,
        public readonly int $position,
    ) {}
}

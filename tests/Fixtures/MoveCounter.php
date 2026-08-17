<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures;

/**
 * Counts `NodeMoved` events across the HTTP boundary.
 *
 * ⚠️ A PROCESS-GLOBAL static, on purpose. `Event::fake()` registers against the
 * test's own container, and a browser test's writes happen inside the served
 * request's container — so a faked dispatcher there would never see them. The
 * listener is registered by the test panel provider, which boots in the served
 * app, and the count lands here where both sides can read it.
 *
 * This exists to prove spec FR-041 / AGENTS.md R-011: a completed interaction
 * fires EXACTLY ONE move event, however many keystrokes produced it. A keyboard
 * move that called the server per arrow press would write one audit row per
 * keystroke for what the user thinks of as a single move.
 */
final class MoveCounter
{
    public static int $moved = 0;

    /**
     * `SiblingsReordered`, counted separately since PA-3.
     *
     * ⚠️ A same-parent reorder now fires THIS rather than `NodeMoved`. Counting
     * both, separately, is what keeps FR-041 provable: the guarantee is "exactly
     * one event for one completed interaction", and a counter that summed them
     * could not tell one reorder from one move — which is the very distinction the
     * amendment exists to restore.
     */
    public static int $reordered = 0;

    public static function reset(): void
    {
        self::$moved = 0;
        self::$reordered = 0;
    }
}

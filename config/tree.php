<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Parent reference column
    |--------------------------------------------------------------------------
    |
    | The nullable self-referencing foreign key on the host's table. `null` in
    | this column means the node is a root.
    |
    */

    'parent_column' => 'parent_id',

    /*
    |--------------------------------------------------------------------------
    | Position column
    |--------------------------------------------------------------------------
    |
    | Order among siblings sharing a parent. Meaningful ONLY within a sibling
    | group — it is not globally unique and is never treated as an identifier.
    |
    */

    'position_column' => 'position',

    /*
    |--------------------------------------------------------------------------
    | Read tie-breaker
    |--------------------------------------------------------------------------
    |
    | A group is read as: position ascending, then this column ascending.
    |
    | ⚠️ This is not cosmetic. Real data violates the contiguity guarantee —
    | legacy rows, rows written before the package was adopted, and rows in
    | groups no move has touched. Without a tie-breaker two nodes sharing a
    | position would order arbitrarily by driver, and the index the package
    | resolves would mean something different from what the actor saw.
    |
    | Set to `null` to fall back to the model's key.
    |
    */

    'tiebreaker' => 'name',

];

<?php

declare(strict_types=1);

return [

    /*
    | Refusals. Each maps to a distinct exception type (data-model.md § Refusals).
    |
    | ⚠️ The three `unreachable_reference` causes deliberately share ONE message,
    | as they share one exception type. Separating them would let a caller — and
    | therefore an actor — distinguish "this node exists but you cannot see it"
    | from "this node does not exist", which is precisely the disclosure FR-037
    | prevents in the ARIA counts.
    */

    'refused' => [
        'cycle' => 'A node cannot be moved inside itself or one of its own descendants.',
        'invalid_target' => 'That destination cannot receive children.',
        'unreachable_reference' => 'That neighbour is not one you could have aimed at.',

        /*
        | ⚠️ PA-2. Unlike the three above this maps to NO exception type: a host's
        | permission answer is a `false`, not a refusal the package raises. It
        | names the node because the actor CAN see it — the disclosure the
        | `unreachable_reference` wording avoids does not arise here, and an
        | unnamed refusal on a page of rows tells the actor nothing.
        */
        'unauthorized' => 'You cannot move :name.',

        /*
        | ⚠️ F23. A reorder naming a key that is not a member of the group it
        | named. A SEPARATE message from `unreachable_reference`, which is
        | deliberately vague across three causes to avoid disclosing whether a
        | hidden node exists — no such disclosure arises here, because the keys
        | are the caller's own claim about a group it already named.
        */
        'not_a_sibling' => 'Those are not all children of the same parent.',
    ],

    /*
    | Keyboard move announcements. Every transition is announced (AGENTS.md R-017).
    |
    | ⚠️ `:name` is the row's OWN name — not its badges, not its action labels, and
    | not its subtree. A row announcing its subtree is the defect four correct
    | aria-* assertions failed to catch in the source application (R-014).
    */

    'announce' => [
        'picked_up' => 'Picked up :name. Use the arrow keys to move it, Enter to put it down, Escape to cancel.',
        'moved' => ':name, position :position of :total.',
        'moved_into' => ':name, position :position of :total, inside :parent.',
        'put_down' => 'Moved :name to position :position of :total.',
        'cancelled' => 'Cancelled. :name returned to its original position.',
        'abandoned' => 'Move abandoned. :name was not moved.',
        'refused' => 'You cannot move :name.',
        'only_child' => ':name is the only child here.',
        'already_first' => ':name is already first.',
        'already_last' => ':name is already last.',
    ],

    /*
    | Page and control labels.
    */

    'search' => [
        'placeholder' => 'Search',
        'label' => 'Search the tree',
    ],

    'drag_handle' => 'Drag :name',

    'confirm' => [
        'heading' => 'Confirm this move',
        'submit' => 'Move',
        'cancel' => 'Cancel',
    ],

    /*
    | ⚠️ TWO empty states, not one (finding F24). `empty` is "there is nothing
    | here"; `empty_search` is "nothing matches what you typed". A tree that says
    | the first while a search is active is lying to the actor, and a host with
    | both sentences previously had to pick one and be wrong in the other state.
    | Chosen by `treeEmptyMessage()`, never by the view.
    */
    'empty' => 'Nothing to show.',
    'empty_search' => 'Nothing matches that search.',

];

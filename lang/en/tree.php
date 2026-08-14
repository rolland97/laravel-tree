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

    'empty' => 'Nothing to show.',

];

@php
$key = $node->getKey();
$children = $grouped[(string) $key] ?? [];
$hasChildren = count($children) > 0;
$rowId = 'ltree-row-'.$key;
$nameId = 'ltree-name-'.$key;

// ⚠️ Taken from the node's OWN group, not from the loop that rendered it. An
// orphan displays among the roots but is still counted among its real
// siblings — see TreePage::treeSetSizeFor().
$position = $this->treePositionFor($node);
$setSize = $this->treeSetSizeFor($node);

// ⚠️ PA-8. The SERVER-rendered state must agree with what the controller will
// answer, because between the response and Alpine booting there is no
// controller: `x-show` has done nothing and `aria-expanded` is whatever the
// markup said. Rendering "open" for a host that starts closed announces the
// wrong state to anything reading the document before boot, and flashes every
// descendant of every branch on first paint.
$startsCollapsed = $this->treeBranchesStartCollapsed();
@endphp

{{--
⚠️ NO WRAPPER ELEMENT (package amendment PA-10). This include emits the row, its
leaf slot and its children group as SIBLINGS, so a `treeitem` is always a direct
child of the `tree` or of a `group` — which is what ARIA means by the tree's
required owned elements.

⚠️ There used to be a `.ltree-branch` div around all three, and `role="tree"`
therefore owned generic divs with the rows as grandchildren. axe passes either
way (its required-children check walks ancestors), so the package's own
accessibility pass proved nothing about it — R-018's lesson about announcements,
arriving through the DOM instead. Found by the first consumer, whose frozen
selectors were written `[role="tree"] > [role="treeitem"]` and stopped matching.

⚠️ The group stays a SIBLING of its row rather than moving inside it. A treeitem
may own a group, but nesting one inside the focusable row would put the entire
subtree into that row's accessible name — the R-014 defect this package exists to
prevent.
--}}
{{--
    ⚠️ role, tabindex and EVERY aria-* property sit on THIS element — the one
    that actually takes focus (constitution Principle IV, AGENTS.md R-013).

    ⚠️ aria-labelledby points at the NAME SPAN ALONE. A treeitem computes its
    name from its CONTENTS, so an unlabelled row announces its badges, all its
    action labels and — expanded — its entire subtree. The source application
    shipped exactly that while four CORRECT aria-* assertions passed, because
    every property was asserted except the one a screen reader leads with
    (AGENTS.md R-014).

    ⚠️ aria-setsize/aria-posinset count the RENDERED siblings, never the true
    group size. That is a PRIVACY requirement before it is a convention: the
    true size discloses that a node exists which the actor may not see
    (AGENTS.md R-015).
--}}
<div
    id="{{ $rowId }}"
    class="ltree-row"
    role="treeitem"
    tabindex="-1"
    x-bind:tabindex="focusedId === @js((string) $key) ? 0 : -1"
    x-bind:class="{ 'ltree-held': heldId === @js((string) $key) }"
    x-bind:aria-grabbed="heldId === @js((string) $key) ? 'true' : null"
    {{-- ⚠️ TWO different questions, two different attributes (PA-13).
         `locked` = this node may not RECEIVE children (`isValidTreeTarget()`);
         `immovable` = this ACTOR may not move this node (`canMoveNode()`). The
         controller refuses a pick-up on the second only — refusing on the first
         made a node closed to new children unreorderable, which no host asked for.
         The server re-decides both regardless. --}}
    @if ($node->isValidTreeTarget() === false) data-ltree-locked="true" @endif
    @if ($this->treeCanMoveNode($node) === false) data-ltree-immovable="true" @endif
    data-ltree-key="{{ $key }}"
    data-ltree-parent="{{ $node->treeParentId() }}"
    aria-labelledby="{{ $nameId }}"
    aria-level="{{ $level }}"
    aria-posinset="{{ $position }}"
    aria-setsize="{{ $setSize }}"
    @if ($hasChildren)
        aria-expanded="{{ $startsCollapsed ? 'false' : 'true' }}"
        x-bind:aria-expanded="isExpanded(@js((string) $key)) ? 'true' : 'false'"
    @endif
>
    {{--
        ⚠️ Drag starts ONLY from this handle, so activating a row action never
        begins a drag (spec FR-023, AGENTS.md R-022).
    --}}
    <span
        class="ltree-handle"
        data-ltree-handle
        draggable="true"
        aria-hidden="true"
        tabindex="-1"
    >⠿</span>

    @if ($hasChildren)
        {{--
            ⚠️ Hidden from assistive technology rather than LABELLED. The row
            already announces its expanded state, and a control duplicating a
            state already announced is noise (spec FR-038, AGENTS.md R-016).
        --}}
        {{--
            ⚠️ A POINTER affordance, and deliberately invisible to assistive
            technology.

            FR-024 and US2 acceptance 1 require branches to be collapsible by a
            mouse user; without this the only way to collapse anything was
            ArrowLeft, which is US3's keyboard path. Hiding this control from
            assistive technology is correct precisely BECAUSE that keyboard
            path exists and is better: the row already announces
            `aria-expanded`, and a labelled chevron would announce the same
            state a second time (spec FR-038, AGENTS.md R-016).

            The click sits on the chevron alone. On the row it would make every
            row action and every drag start also toggle the branch.
        --}}
        <span
            class="ltree-chevron"
            data-ltree-chevron
            x-bind:class="isExpanded(@js((string) $key)) ? 'ltree-chevron-open' : ''"
            x-on:click.stop="toggleBranch(@js((string) $key))"
            aria-hidden="true"
            tabindex="-1"
        >›</span>
    @endif

    <span id="{{ $nameId }}" class="ltree-row-name">{{ $node->getAttribute(\Rolland\Tree\Support\TreeColumns::tiebreaker() ?? $node->getKeyName()) }}</span>

    @foreach ($this->treeBadgesFor($node) as $badge)
        <x-filament::badge>{{ $badge }}</x-filament::badge>
    @endforeach

    @foreach ($this->treeRowActions($node) as $action)
        {{ $action }}
    @endforeach
</div>

@if ($leaf = $this->treeLeafSlot($node))
    <div class="ltree-children">{{ $leaf }}</div>
@endif

@if ($hasChildren)
    {{--
        ⚠️ `x-show`, not `x-if`. A collapsed branch must stay in the DOM: the
        rows inside it are still members of their group, and removing them
        would make the rendered set the package reports back to the server
        depend on what the user happened to have open.
    --}}
    <div
        class="ltree-children"
        role="group"
        data-ltree-children-of="{{ $key }}"
        x-show="isExpanded(@js((string) $key))"
        {{-- ⚠️ The initial state, in the markup (PA-8). Alpine clears this
             itself the moment `x-show` evaluates truthy, so a host that starts
             open is untouched — but a host that starts closed no longer paints
             its whole tree and then hides it. --}}
        @if ($startsCollapsed) style="display: none;" @endif
    >
        @foreach ($children as $child)
            @include('tree::tree-branch', [
                'node' => $child,
                'grouped' => $grouped,
                'level' => $level + 1,
            ])
        @endforeach
    </div>
@endif

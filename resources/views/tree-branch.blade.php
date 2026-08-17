@php
    $key = $node->getKey();
    $children = $grouped[(string) $key] ?? [];
    $hasChildren = count($children) > 0;
    $rowId = 'ltree-row-'.$key;
    $nameId = 'ltree-name-'.$key;
@endphp

<div class="ltree-branch" data-ltree-branch="{{ $key }}">
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
        data-ltree-key="{{ $key }}"
        data-ltree-parent="{{ $node->treeParentId() }}"
        aria-labelledby="{{ $nameId }}"
        aria-level="{{ $level }}"
        aria-posinset="{{ $position }}"
        aria-setsize="{{ $setSize }}"
        @if ($hasChildren)
            aria-expanded="true"
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
            <span
                class="ltree-chevron"
                x-bind:class="isExpanded(@js((string) $key)) ? 'ltree-chevron-open' : ''"
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
        >
            @foreach ($children as $child)
                @include('tree::tree-branch', [
                    'node' => $child,
                    'grouped' => $grouped,
                    'level' => $level + 1,
                    'position' => $loop->iteration,
                    'setSize' => count($children),
                ])
            @endforeach
        </div>
    @endif
</div>

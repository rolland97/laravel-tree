{{--
    ⚠️ WRITTEN FROM SCRATCH against package-owned classes, not ported.

    Every visual class here is `ltree-` prefixed and defined in this package's own
    compiled stylesheet. A Filament panel compiles only the utilities its OWN css
    references, so a blade living in vendor/ that used bare host utility classes
    would render unstyled — and the failure is SILENT, reading as a bug in the
    host's css (constitution Principle VI, AGENTS.md R-019/R-020). Anything
    Filament already covers uses <x-filament::*>.
--}}
{{--
    ⚠️ THE TREE AS CONTENT, NOT AS A PAGE (package amendment PA-19).

    This file holds everything the tree is. `tree::tree` is now a thin wrapper that
    puts it inside `<x-filament-panels::page>`, and a host that needs the tree beside
    something else — a breadcrumb, a contents pane — overrides `getView()` with its
    own layout and includes THIS view:

        <x-filament-panels::page>
            <x-my-breadcrumb />
            <div class="my-grid">
                <aside>@include('tree::tree-content')</aside>
                <main><x-my-contents-pane /></main>
            </div>
        </x-filament-panels::page>

    ⚠️ Before the split the only view the package shipped was a whole page, so a host
    needing a second region had to REPRODUCE what is below — the controller wrapper,
    the live region, the search toolbar, the confirmation — and `README.md` excludes
    the package's blades from its public surface. The supported answer was "you
    cannot". The second consumer needed exactly that (074 FR-001).

    ⚠️ Same shape as PA-1, which made the tree a TRAIT because a host needed a
    different CLASS shell. This is a different LAYOUT shell. One body, many shells.

    ⚠️ **`tree::tree-content` IS public. `tree::tree-branch` is NOT** — it is
    recursive, takes three required variables, and is an implementation detail of
    this file.

    ⚠️ MUST NOT open a page component of its own. A host includes this inside its own
    page, and a second `<x-filament-panels::page>` here would nest one page inside
    another. `ComposableViewTest` pins that by counting `fi-page`.
--}}
<div
    {{--
        ⚠️ The initial collapse state is the SECOND argument (PA-8), passed the
        same way the strings are: server-side, host-controlled, no $wire round
        trip for something the server already knows.

        ⚠️ And whether the page HAS ordering is the third (PA-18), for the same
        reason. It has to reach the controller and not only the markup: removing
        the handle takes the pointer affordance away, but the Space binding lives
        here, and a keyboard actor on a page with no order must not be able to
        pick anything up.
    --}}
    x-data="ltree(@js($this->treeStrings()), @js($this->treeBranchesStartCollapsed()), @js($this->treeReorderEnabled()))"
    x-on:keydown="onTreeKeydown($event)"
    x-on:focusout="onTreeFocusOut($event)"
    class="ltree-root"
>
    {{--
        The live region carries every announcement.

        ⚠️ `wire:ignore` is not decoration. Its content is CLIENT state the
        server knows nothing about, so a re-render the package did not initiate
        wipes it (AGENTS.md R-017). It must be in the accessibility tree but
        not visible, so it is clipped rather than `display: none` — a hidden
        region is not announced.
    --}}
    <div
        wire:ignore
        class="ltree-live-region"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        x-text="announcement"
    ></div>

    <div class="ltree-toolbar">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="treeSearch"
                :placeholder="__('tree::tree.search.placeholder')"
                :aria-label="__('tree::tree.search.label')"
            />
        </x-filament::input.wrapper>
    </div>

    @php($grouped = $this->nodesByParent())
    {{-- ⚠️ NOT $grouped[''] — see TreePage::treeDisplayRoots(). A node whose
         parent the host's query did not return must render at the root of what
         the actor CAN see, or it vanishes entirely. --}}
    @php($roots = $this->treeDisplayRoots())

    @if (count($roots) === 0)
        <p class="ltree-empty">{{ $this->treeEmptyMessage() }}</p>
    @else
        {{--
            ⚠️ role="tree" and every treeitem's role/tabindex/aria-* sit on the
            SAME element — the one that actually takes focus (AGENTS.md R-013).
        --}}
        {{--
            ⚠️ `treeAccessibleName()`, NOT `getNavigationLabel()` (PA-7). A
            navigation item answers "where am I going"; this answers "what is
            this control". They were the same string, so a host with two could
            only have one — see the slot's own note.
        --}}
        <div class="ltree-tree" role="tree" aria-label="{{ $this->treeAccessibleName() }}">
            @foreach ($roots as $node)
                @include('tree::tree-branch', [
                    'node' => $node,
                    'grouped' => $grouped,
                    'level' => 1,
                ])
            @endforeach
        </div>
    @endif

    @if ($pendingMove)
        {{--
            ⚠️ An inline region, NOT <x-filament::modal>.

            Filament's modal opens through its own Alpine machinery, which did
            not fire for a package-served panel — the markup was in the DOM but
            never made visible, so a confirmation the user could not see would
            have silently blocked every drag. A package should not depend on a
            host component's JS lifecycle to show something this important.

            role="alertdialog" + aria-modal carry the same meaning to assistive
            technology, and the region is plain DOM that is either there or not.
        --}}
        {{--
            ⚠️ `tabindex="-1"` + `x-init` because an alertdialog that never
            receives focus is announced only by the assistive technology that
            volunteers it, and reached by a keyboard actor only by tabbing
            forward blind. Measured in a real consumer panel: the confirmation
            opened and `document.activeElement` was still the drag handle of the
            row just dragged (the consumer's adoption, T057, finding F39).

            Focusing the region rather than the submit button: the heading is
            what the actor needs read to them, and pre-focusing "Move it" would
            put the destructive answer under the next Enter press.
        --}}
        <div
            class="ltree-confirm"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="ltree-confirm-heading"
            tabindex="-1"
            x-init="$nextTick(() => $el.focus())"
            data-ltree-confirm
            data-ltree-confirm-node="{{ $pendingMove['nodeKey'] }}"
        >
            <h2 id="ltree-confirm-heading" class="ltree-confirm-heading">
                {{-- PA-6: the host may name its own question; otherwise ours. --}}
                {{ $pendingMove['heading'] ?? __('tree::tree.confirm.heading') }}
            </h2>

            <p class="ltree-confirm-message">{{ $pendingMove['message'] }}</p>

            <div class="ltree-confirm-actions">
                <x-filament::button wire:click="confirmPendingMove" data-ltree-confirm-submit>
                    {{ __('tree::tree.confirm.submit') }}
                </x-filament::button>

                <x-filament::button color="gray" wire:click="cancelPendingMove" data-ltree-confirm-cancel>
                    {{ __('tree::tree.confirm.cancel') }}
                </x-filament::button>
            </div>
        </div>
    @endif

</div>

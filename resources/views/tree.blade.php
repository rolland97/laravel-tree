{{--
    ⚠️ WRITTEN FROM SCRATCH against package-owned classes, not ported.

    Every visual class here is `ltree-` prefixed and defined in this package's own
    compiled stylesheet. A Filament panel compiles only the utilities its OWN css
    references, so a blade living in vendor/ that used bare host utility classes
    would render unstyled — and the failure is SILENT, reading as a bug in the
    host's css (constitution Principle VI, AGENTS.md R-019/R-020). Anything
    Filament already covers uses <x-filament::*>.
--}}
<x-filament-panels::page>
    <div
        x-data="ltree(@js($this->treeStrings()))"
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
            <div class="ltree-tree" role="tree" aria-label="{{ static::getNavigationLabel() }}">
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
            <div
                class="ltree-confirm"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="ltree-confirm-heading"
                data-ltree-confirm
            >
                <h2 id="ltree-confirm-heading" class="ltree-confirm-heading">
                    {{ __('tree::tree.confirm.heading') }}
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
</x-filament-panels::page>

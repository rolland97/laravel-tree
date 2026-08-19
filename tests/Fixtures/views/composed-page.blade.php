{{--
    A HOST's own page layout, composing the tree into two regions (PA-19).

    ⚠️ The point of the fixture is that this file is the HOST's, not the package's.
    It owns the page wrapper and the layout; it includes the tree as content. Before
    PA-19 a host wanting this had to reproduce the package's inner markup — the
    `x-data` controller, the live region, the search toolbar, the confirmation — which
    the README excludes from the public surface.

    ⚠️ `data-host-region` markers exist so a test can prove the tree rendered INSIDE
    the host's own layout rather than instead of it.
--}}
<x-filament-panels::page>
    <div data-host-region="breadcrumb">Host breadcrumb</div>

    <div data-host-region="body">
        <aside data-host-region="sidebar">
            @include('tree::tree-content')
        </aside>

        <main data-host-region="pane">Host contents pane</main>
    </div>
</x-filament-panels::page>

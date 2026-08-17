{{--
    R10 spike page.

    ⚠️ The probe below reads `background-color`, not `outline-style`. A probe on a
    bare element reading `outline-style` cannot fail, because its default is
    already `none` — which is exactly how the source application concluded a
    utility had compiled while the dev server was serving no CSS at all
    (quickstart.md § SC-005). A transparent default cannot be mistaken for
    `rgb(220, 38, 38)`.
--}}
<x-filament-panels::page>
    <div id="ltree-spike-probe" class="ltree-spike-probe">
        served from resources/dist/tree.css
    </div>

    <div class="ltree-tree" role="tree" aria-label="Spike tree">
        <div id="ltree-spike-row" class="ltree-row" role="treeitem" tabindex="0"
             aria-level="1" aria-posinset="1" aria-setsize="1">
            <span class="ltree-row-name">Alpha</span>
        </div>
    </div>
</x-filament-panels::page>

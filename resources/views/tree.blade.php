{{--
    ⚠️ THE DEFAULT PAGE, and since PA-19 a thin wrapper over `tree::tree-content`.

    The tree itself lives in that file. This one exists so a host that wants the tree
    to BE the page — which is every host that existed before PA-19 — keeps getting
    exactly that from `getView()`'s default, with no change of its own.

    ⚠️ Deliberately nothing but the wrapper. Anything added here would be invisible to
    a host composing its own layout, and the two would drift — which is the failure
    PA-1 already taught this package once, when a base class and a trait held two
    copies of one body.
--}}
<x-filament-panels::page>
    @include('tree::tree-content')
</x-filament-panels::page>

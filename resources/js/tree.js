/*
 * The tree's Alpine controller.
 *
 * ⚠️ Registered as a Filament AlpineComponent asset (research R6), NOT inlined in
 * a blade — an inline controller cannot be built, minified or cached, and the
 * source application's version was inline and had grown past the point of being
 * reviewable.
 *
 * ⚠️ Translated strings arrive as the ARGUMENT to this factory —
 * `x-data="ltree(@js($this->treeStrings()))"` — not through a `$wire` round trip
 * for strings the server already has, and not through a JSON data attribute
 * (research R7). Fewer string-handling layers is a correctness argument here: a
 * sibling slice mangled a translation placeholder in a way that still rendered
 * correctly, so every assertion passed.
 *
 * Traversal, roving tabindex and keyboard reordering land in T071–T072 and
 * T087–T092. This file currently carries only what the R10 spike needs.
 */
export default function ltree(strings = {}) {
    return {
        strings,

        /** The row that currently owns the tree's single tab stop. */
        focusedId: null,

        /** The node being carried in a keyboard move, if any. */
        heldId: null,

        /** The most recent announcement. Client state the server knows nothing about. */
        announcement: '',

        init() {
            //
        },

        /**
         * ⚠️ The live region's content is CLIENT state. A re-render the package did
         * not initiate wipes it unless the region is excluded from morphing, and a
         * morph hook that does not check WHICH component morphed silently abandons
         * every held node when a host panel polls a notification bell. Both are
         * wired in T089/T090.
         */
        announce(message) {
            this.announcement = message
        },
    }
}

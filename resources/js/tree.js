/*
 * The tree's Alpine controller.
 *
 * ⚠️ Registered as a Filament AlpineComponent asset (research R6), NOT inlined in
 * a blade — an inline controller cannot be built, minified or cached, and the
 * source application's version was inline and had grown past being reviewable.
 *
 * ⚠️ Translated strings arrive as the ARGUMENT to this factory —
 * `x-data="ltree(@js($this->treeStrings()))"` — not through a `$wire` round trip
 * for strings the server already has, and not through a JSON data attribute
 * (research R7). Fewer string-handling layers is a correctness argument: a
 * sibling slice mangled a translation placeholder in a way that still rendered
 * correctly, so every assertion passed.
 */

/** Where a pointer sitting at `ratio` down a row wants the node to land. */
const NEST_BAND_START = 0.25
const NEST_BAND_END = 0.75

function ltree(strings = {}) {
    return {
        strings,

        /** The row that owns the tree's single tab stop. */
        focusedId: null,

        /** The node being carried in a keyboard move, if any. */
        heldId: null,

        /** The most recent announcement. Client state the server knows nothing about. */
        announcement: '',

        /** The node being dragged with a pointer, if any. */
        draggingId: null,

        /** Branch keys the user has closed. Client state; the server has no opinion. */
        collapsed: {},

        init() {
            this.adoptFirstRow()

            this.$el.addEventListener('dragstart', (event) => this.onDragStart(event))
            this.$el.addEventListener('dragover', (event) => this.onDragOver(event))
            this.$el.addEventListener('dragleave', (event) => this.onDragLeave(event))
            this.$el.addEventListener('drop', (event) => this.onDrop(event))
            this.$el.addEventListener('dragend', () => this.clearDrag())
        },

        // ── Reading the rendered tree ────────────────────────────────────────
        //
        // ⚠️ Everything below reads the DOM, because the DOM is the only place that
        // knows what the actor was actually SHOWN. That set is then sent to the
        // server as `renderedSiblingIds` — never as an index. The browser cannot
        // count rows it never drew (constitution Principle II).

        rows() {
            return Array.from(this.$el.querySelectorAll('[data-ltree-key]'))
        },

        rowFor(key) {
            return this.$el.querySelector(`[data-ltree-key="${key}"]`)
        },

        parentKeyOf(row) {
            const value = row?.dataset.ltreeParent
            return value === undefined || value === '' ? null : value
        },

        /** The ids of the rows RENDERED in one group, in display order. */
        renderedSiblingIds(parentKey) {
            const selector = parentKey === null || parentKey === ''
                ? '[data-ltree-key][data-ltree-parent=""]'
                : `[data-ltree-key][data-ltree-parent="${parentKey}"]`

            return Array.from(this.$el.querySelectorAll(selector)).map((row) => row.dataset.ltreeKey)
        },

        adoptFirstRow() {
            const first = this.rows()[0]

            if (first && this.focusedId === null) {
                this.focusedId = first.dataset.ltreeKey
            }
        },

        // ── Pointer drag ─────────────────────────────────────────────────────

        onDragStart(event) {
            // ⚠️ A drag begins ONLY from the dedicated handle, so activating a row
            // action never starts one (spec FR-023, AGENTS.md R-022).
            const handle = event.target.closest('[data-ltree-handle]')

            if (!handle) {
                event.preventDefault()
                return
            }

            const row = handle.closest('[data-ltree-key]')

            if (!row) {
                event.preventDefault()
                return
            }

            this.draggingId = row.dataset.ltreeKey
            event.dataTransfer?.setData('text/plain', this.draggingId)

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move'
            }
        },

        onDragOver(event) {
            if (this.draggingId === null) {
                return
            }

            const target = this.targetFrom(event)

            if (!target) {
                return
            }

            // Required, or the browser refuses the drop.
            event.preventDefault()

            this.clearDropMarkers()
            target.row.classList.add('ltree-drop-target')
        },

        onDragLeave(event) {
            const row = event.target.closest?.('[data-ltree-key]')
            row?.classList.remove('ltree-drop-target')
        },

        onDrop(event) {
            if (this.draggingId === null) {
                return
            }

            const target = this.targetFrom(event)
            const nodeKey = this.draggingId

            this.clearDrag()

            if (!target) {
                return
            }

            event.preventDefault()

            this.commit(nodeKey, target)
        },

        /**
         * Which placement the POINTER POSITION is asking for.
         *
         * ⚠️ Position, never an index. Top quarter of a row means "before this
         * row", bottom quarter means "after it", and the middle half means "inside
         * it" — all three name a NEIGHBOUR or a PARENT, which is the only thing the
         * server accepts.
         */
        targetFrom(event) {
            const row = event.target.closest?.('[data-ltree-key]')

            if (!row || row.dataset.ltreeKey === this.draggingId) {
                return null
            }

            // A node may not be dropped inside its own subtree; the server refuses
            // it anyway, but refusing here avoids a pointless round trip.
            const dragged = this.rowFor(this.draggingId)
            if (dragged && dragged.parentElement?.parentElement?.contains(row)) {
                const branch = dragged.closest('[data-ltree-branch]')
                if (branch && branch.contains(row)) {
                    return null
                }
            }

            const box = row.getBoundingClientRect()
            const ratio = box.height === 0 ? 0.5 : (event.clientY - box.top) / box.height

            if (ratio >= NEST_BAND_START && ratio <= NEST_BAND_END) {
                return {
                    row,
                    destinationParentKey: row.dataset.ltreeKey,
                    referenceKey: null,
                    placement: 'LastChild',
                }
            }

            return {
                row,
                destinationParentKey: this.parentKeyOf(row),
                referenceKey: row.dataset.ltreeKey,
                placement: ratio < NEST_BAND_START ? 'Before' : 'After',
            }
        },

        commit(nodeKey, target) {
            const rendered = this.renderedSiblingIds(target.destinationParentKey)

            this.$wire.placeNode(
                nodeKey,
                target.destinationParentKey,
                target.referenceKey,
                target.placement,
                rendered,
            )
        },

        clearDropMarkers() {
            this.$el.querySelectorAll('.ltree-drop-target').forEach((row) => {
                row.classList.remove('ltree-drop-target')
            })
        },

        clearDrag() {
            this.draggingId = null
            this.clearDropMarkers()
        },

        // ── Keyboard traversal (US3) ─────────────────────────────────────────

        isExpanded(key) {
            return this.collapsed[key] !== true
        },

        childrenContainerOf(key) {
            return this.$el.querySelector(`[data-ltree-children-of="${key}"]`)
        },

        hasChildren(row) {
            return this.childrenContainerOf(row.dataset.ltreeKey) !== null
        },

        /**
         * Rows the actor can actually see right now.
         *
         * ⚠️ Arrows traverse DISPLAYED rows. A row inside a collapsed branch is
         * still in the DOM — deliberately, so the rendered set the package reports
         * to the server does not depend on what the user happened to have open —
         * so it has to be filtered out here rather than assumed absent.
         */
        displayedRows() {
            return this.rows().filter((row) => !this.isHiddenByCollapse(row))
        },

        isHiddenByCollapse(row) {
            let container = row.parentElement?.closest('[data-ltree-children-of]')

            while (container) {
                if (!this.isExpanded(container.dataset.ltreeChildrenOf)) {
                    return true
                }

                container = container.parentElement?.closest('[data-ltree-children-of]')
            }

            return false
        },

        parentRowOf(row) {
            const container = row.parentElement?.closest('[data-ltree-children-of]')

            return container ? this.rowFor(container.dataset.ltreeChildrenOf) : null
        },

        firstChildRowOf(row) {
            const container = this.childrenContainerOf(row.dataset.ltreeKey)

            return container ? container.querySelector('[data-ltree-key]') : null
        },

        focusRow(row) {
            if (!row) {
                return
            }

            this.focusedId = row.dataset.ltreeKey

            // ⚠️ After $nextTick: focus() on a hidden element is a SILENT no-op,
            // and a row revealed by the expand in this same keystroke may not be
            // displayed yet when we get here.
            this.$nextTick(() => row.focus())
        },

        step(row, delta) {
            const displayed = this.displayedRows()
            const index = displayed.indexOf(row)

            if (index === -1) {
                return
            }

            // ⚠️ NO WRAPPING, at either end (spec FR-032). Wrapping in a tree is
            // disorienting: the reader has no way to tell "last row" from "back at
            // the top" without counting.
            const next = index + delta

            if (next < 0 || next >= displayed.length) {
                return
            }

            this.focusRow(displayed[next])
        },

        onTreeKeydown(event) {
            const row = event.target.closest?.('[data-ltree-key]')

            if (!row) {
                return
            }

            const handlers = {
                ArrowDown: () => this.step(row, 1),
                ArrowUp: () => this.step(row, -1),
                Home: () => this.focusRow(this.displayedRows()[0]),
                End: () => {
                    const displayed = this.displayedRows()
                    this.focusRow(displayed[displayed.length - 1])
                },
                // Right EXPANDS first, and only descends once already open.
                ArrowRight: () => {
                    if (!this.hasChildren(row)) {
                        return
                    }

                    if (!this.isExpanded(row.dataset.ltreeKey)) {
                        this.collapsed = { ...this.collapsed, [row.dataset.ltreeKey]: false }

                        return
                    }

                    this.focusRow(this.firstChildRowOf(row))
                },
                // Left COLLAPSES first, and only ascends once already closed.
                ArrowLeft: () => {
                    if (this.hasChildren(row) && this.isExpanded(row.dataset.ltreeKey)) {
                        this.collapsed = { ...this.collapsed, [row.dataset.ltreeKey]: true }

                        return
                    }

                    this.focusRow(this.parentRowOf(row))
                },
            }

            const handler = handlers[event.key]

            if (!handler) {
                return
            }

            event.preventDefault()
            handler()
        },

        onTreeFocusOut() {
            // Abandonment announcement lands in T081/T087.
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

/*
 * ⚠️ Registered eagerly on `alpine:init`, and shipped as a plain Js asset rather
 * than a Filament AlpineComponent.
 *
 * The AlpineComponent route relies on Filament's async `x-load` directive to
 * import the module at first use. That never initialised the component in the
 * package's own Testbench-served panel — no JavaScript error, no console log, the
 * page rendered perfectly and the drag simply did nothing. A silent failure is
 * the worst kind for this package specifically (see research R6 and R10 for two
 * others already paid for), so the loading path is the one with no moving parts:
 * the file is always present and registers itself.
 */
const registerLtree = () => {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('ltree', ltree)
    }
}

// ⚠️ BOTH paths, because the order is not ours to control. If this asset is
// evaluated before Alpine starts, `alpine:init` fires later and registers it; if
// Filament has already started Alpine by the time we run, that event has been and
// gone and only the immediate call gets us registered. Relying on one alone fails
// silently — the page renders, nothing is bound, and the tree simply does nothing.
document.addEventListener('alpine:init', registerLtree)
registerLtree()

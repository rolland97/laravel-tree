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

        /**
         * The keyboard hold. ⚠️ ALL of this is client state, and NONE of it reaches
         * the server until the drop (T087). A call per arrow press would write one
         * audit row per keystroke for what the user thinks of as one move.
         */
        heldSiblings: [],
        heldIndex: 0,
        heldOrigin: 0,

        init() {
            this.adoptFirstRow()

            this.$el.addEventListener('dragstart', (event) => this.onDragStart(event))
            this.$el.addEventListener('dragover', (event) => this.onDragOver(event))
            this.$el.addEventListener('dragleave', (event) => this.onDragLeave(event))
            this.$el.addEventListener('drop', (event) => this.onDrop(event))
            this.$el.addEventListener('dragend', () => this.clearDrag())

            this.guardAgainstForeignMorphs()

            // ⚠️ Reports raised by the SERVER — an only-child no-op, a refusal —
            // reach the live region through here. Without this the page dispatches
            // into nothing and a screen-reader user hears silence where a sighted
            // user sees a notification.
            window.addEventListener('ltree-announce', (event) => {
                const detail = event.detail ?? {}
                const message = detail.message ?? (Array.isArray(detail) ? detail[0] : null)

                if (message) {
                    this.announce(message)
                }
            })
        },

        /**
         * ⚠️ Check WHICH component morphed before reacting to a morph at all.
         *
         * Without this, a host panel polling a notification bell every 30 seconds
         * silently abandons every held node — a defect the source application
         * shipped. And scoping by component id is not optional extra care: dropping
         * the check rather than fixing it is HOW that defect got in.
         *
         * The hold lives in Alpine state on an element Livewire does not replace, so
         * the correct behaviour for a foreign morph is to do nothing at all. This
         * hook exists to make that explicit and to keep the reasoning attached to
         * the code, rather than leaving the absence of a hook to look accidental.
         */
        guardAgainstForeignMorphs() {
            if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
                return
            }

            const ours = this.$el.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null

            window.Livewire.hook('morph', ({ component }) => {
                if (!ours || component?.id !== ours) {
                    // A DIFFERENT component re-rendered. Not our business, and
                    // certainly not a reason to drop what the user is carrying.
                    return
                }
            })
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

        /**
         * Toggle a branch from the POINTER.
         *
         * ⚠️ Reassigns `collapsed` rather than mutating it. Alpine tracks the
         * object, and an in-place `this.collapsed[key] = x` on a plain object
         * property does not always re-run the bindings that read it — the branch
         * would collapse in state and stay open on screen.
         */
        toggleBranch(key) {
            this.collapsed = { ...this.collapsed, [key]: this.isExpanded(key) }
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

        // ── Announcements (US4) ──────────────────────────────────────────────

        nameOf(key) {
            return this.rowFor(key)?.querySelector('.ltree-row-name')?.textContent?.trim() ?? ''
        },

        /**
         * ⚠️ Templates come from the SERVER, as the argument to this factory
         * (research R7). Only the substitution happens here, so a host that
         * translates `tree::tree.announce.*` changes what is heard without touching
         * any JavaScript.
         */
        say(template, replacements = {}) {
            const text = this.strings?.[template]

            if (!text) {
                return
            }

            this.announce(
                Object.entries(replacements).reduce(
                    (carry, [token, value]) => carry.split(`:${token}`).join(value),
                    text,
                )
            )
        },

        // ── The keyboard hold (US4) ──────────────────────────────────────────

        siblingRowsOf(row) {
            const parentKey = row.dataset.ltreeParent ?? ''
            const selector = `[data-ltree-key][data-ltree-parent="${parentKey}"]`

            return Array.from(this.$el.querySelectorAll(selector))
                .filter((candidate) => !this.isHiddenByCollapse(candidate))
        },

        pickUp(row) {
            // A courtesy refusal only. The REAL guard is placeNode() re-checking
            // the host's authorization on the committing call.
            if (row.dataset.ltreeLocked === 'true') {
                this.say('refused', { name: this.nameOf(row.dataset.ltreeKey) })

                return
            }

            const siblings = this.siblingRowsOf(row).map((r) => r.dataset.ltreeKey)

            this.heldId = row.dataset.ltreeKey
            this.heldSiblings = siblings
            this.heldIndex = siblings.indexOf(this.heldId)
            this.heldOrigin = this.heldIndex

            this.say('picked_up', this.heldContext())
        },

        /**
         * ⚠️ The substitutions for every announcement raised WHILE A NODE IS HELD
         * (package amendment PA-4).
         *
         * Four keys — picked_up, cancelled, already_first, already_last — were
         * handed only `:name`, so a host whose wording used `:position` or
         * `:total` had the literal text ":position" announced to a screen-reader
         * user. It rendered fine and passed every assertion.
         *
         * ⚠️ The package's own sweep cannot catch that: it checks the package's
         * lang file against the package's token list, and the package's own
         * wording does not use the missing tokens. The mismatch only exists once a
         * HOST overrides.
         *
         * One rule rather than four fixes: while a node is held the controller
         * knows all three, so it always passes all three. Extra replacements a
         * template does not use are ignored, so this is additive for every
         * existing host.
         */
        heldContext(overrides = {}) {
            return {
                name: this.nameOf(this.heldId),
                position: this.heldIndex + 1,
                total: this.heldSiblings.length,
                ...overrides,
            }
        },

        moveHeld(delta) {
            const total = this.heldSiblings.length

            if (total <= 1) {
                this.say('only_child', this.heldContext())

                return
            }

            const next = this.heldIndex + delta

            if (next < 0) {
                this.say('already_first', this.heldContext())

                return
            }

            if (next >= total) {
                this.say('already_last', this.heldContext())

                return
            }

            this.heldIndex = next

            this.say('moved', this.heldContext())
        },

        putDown() {
            const heldId = this.heldId
            const row = this.rowFor(heldId)

            if (!row) {
                this.releaseHold()

                return
            }

            const others = this.heldSiblings.filter((key) => key !== heldId)
            const index = this.heldIndex

            // ⚠️ A NEIGHBOUR, never an index. The server is never told "position 2";
            // it is told "before Charlie" or "after Delta", and resolves that against
            // the complete group itself (constitution Principle II).
            const target = index === 0
                ? { referenceKey: others[0], placement: 'Before' }
                : { referenceKey: others[index - 1], placement: 'After' }

            const destinationParentKey = row.dataset.ltreeParent || null

            this.say('put_down', this.heldContext())
            this.releaseHold()

            if (target.referenceKey === undefined) {
                return
            }

            // ⚠️ THE SAME helper the pointer release uses (T088). Exactly one copy
            // of the placement contract, so the keyboard cannot drift from the drag.
            this.commit(heldId, {
                destinationParentKey,
                referenceKey: target.referenceKey,
                placement: target.placement,
            })
        },

        cancelHold() {
            // ⚠️ The ORIGINAL position, captured before releaseHold() clears it.
            // The node returns to where it started, so announcing where the
            // abandoned move had walked it to would tell a non-sighted user it is
            // somewhere it is not.
            const context = this.heldContext({ position: this.heldOrigin + 1 })
            this.releaseHold()
            this.say('cancelled', context)
        },

        abandonHold() {
            const context = this.heldContext({ position: this.heldOrigin + 1 })
            this.releaseHold()
            this.say('abandoned', context)
        },

        releaseHold() {
            this.heldId = null
            this.heldSiblings = []
            this.heldIndex = 0
            this.heldOrigin = 0
        },

        onTreeKeydown(event) {
            const row = event.target.closest?.('[data-ltree-key]')

            if (!row) {
                return
            }

            // ⚠️ While a node is HELD the arrows mean something else entirely, so
            // traversal must not also run. Handled first, and exclusively.
            if (this.heldId !== null) {
                const held = {
                    ArrowUp: () => this.moveHeld(-1),
                    ArrowDown: () => this.moveHeld(1),
                    Enter: () => this.putDown(),
                    ' ': () => this.putDown(),
                    Escape: () => this.cancelHold(),
                }[event.key]

                if (held) {
                    event.preventDefault()
                    held()
                }

                return
            }

            if (event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault()
                this.pickUp(row)

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

        /**
         * ⚠️ Focus leaving the tree mid-hold ABANDONS the move and says so.
         *
         * Silently keeping the hold would leave a node carried by a user who has
         * moved on, and the next arrow press anywhere would move it.
         */
        onTreeFocusOut(event) {
            if (this.heldId === null) {
                return
            }

            const goingTo = event?.relatedTarget ?? document.activeElement

            // ⚠️ Containment is checked against the role="tree" element, NOT the
            // component root. The root also holds the search box and the live
            // region, so testing against it would treat "tabbed into the search
            // field" as "still in the tree" and leave the node held while the user
            // typed — every keystroke then landing somewhere unexpected.
            const tree = this.$el.querySelector('[role="tree"]')

            if (goingTo && tree && tree.contains(goingTo)) {
                return
            }

            this.abandonHold()
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

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

function ltree(strings = {}, startCollapsed = false, reorderEnabled = true) {
    return {
        strings,

        /**
         * Whether a branch nobody has touched is closed (PA-8).
         *
         * ⚠️ The host's answer, passed as the second argument for the same reason
         * the strings are the first: the server already knows it. The default is
         * `false`, which is what every host got before the slot existed.
         */
        startCollapsed,

        /**
         * Whether this page offers sibling ordering at all (PA-18).
         *
         * ⚠️ The host's answer, passed as the third argument for the same reason the
         * strings and the collapse state are: the server already knows it. The default
         * is `true`, which is what every host got before the slot existed.
         *
         * ⚠️ NOT the same question as `data-ltree-immovable`. That attribute says this
         * ACTOR may not move this NODE, and `pickUp()` refuses it with an announcement
         * saying so. This says the PAGE has no order, so there is nothing to refuse and
         * nothing to say — the binding simply is not there.
         */
        reorderEnabled,

        /** The row that owns the tree's single tab stop. */
        focusedId: null,

        /** The node being carried in a keyboard move, if any. */
        heldId: null,

        /** The most recent announcement. Client state the server knows nothing about. */
        announcement: '',

        /** The node being dragged with a pointer, if any. */
        draggingId: null,

        /**
         * Branch keys the user has EXPLICITLY opened (`false`) or closed (`true`).
         * Client state; the server has no opinion about it.
         *
         * ⚠️ A key that is absent means "untouched", and that is the case the host's
         * `startCollapsed` answers — see `isExpanded()`.
         */
        collapsed: {},

        /**
         * True while `placeHeldAt()` is rearranging the DOM (PA-15).
         *
         * ⚠️ This is not bookkeeping, it is the reason the preview could not simply be
         * added. Moving a FOCUSED element fires `focusout`, `onTreeFocusOut()` reads
         * that as the actor leaving the tree, and the hold was abandoned by the very
         * move that was previewing it — `moveHeld()` then announced over an emptied
         * state and said ", position 1 of 0.". The move must be able to say "this blur
         * is mine".
         */
        movingPreview: false,

        /**
         * Which row held DOM focus when our own morph began, if any (PA-14).
         *
         * ⚠️ `null` is the normal case AND the safe one — see restoreFocusAfterMorph().
         */
        focusWasOnRow: null,

        /**
         * The node key of the confirmation on screen when our morph began, if any.
         *
         * ⚠️ A SECOND field rather than a flag on `focusWasOnRow`, because the two
         * restore to different places: a row restores to itself, and an answered
         * confirmation restores to the row its move was about — the confirmation is
         * gone by the time the morph finishes (F39).
         */
        confirmWasShowing: null,

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

                // ⚠️ Recorded BEFORE the DOM changes (PA-14): once morphdom has
                // replaced the row, the answer to "did a row have focus" is gone.
                const focused = document.activeElement

                this.focusWasOnRow = focused instanceof Element
                    && this.$el.contains(focused)
                    && focused.matches('[data-ltree-key]')
                    ? focused.dataset.ltreeKey
                    : null

                // ⚠️ The confirmation is recorded by its PRESENCE, not by its focus.
                //
                // Measured: pressing an answer fires `focusout` from the dialog to
                // the button, then from the button to NOTHING — Livewire disables the
                // button it is submitting, and a disabled element cannot hold focus.
                // By the time this hook runs `document.activeElement` is already
                // `<body>`, so a focus-based test here sees nothing at all (F39).
                //
                // ⚠️ Nor on the buttons' own click, which was the first fix and was
                // WRONG: it works only once Alpine has bound the handler, and a click
                // that lands before that silently skips it. A human cannot click that
                // fast and a test can, which is the kind of race that reads as a flake
                // for weeks.
                this.confirmWasShowing = this.$el
                    .querySelector('[data-ltree-confirm]')
                    ?.dataset.ltreeConfirmNode ?? null
            })

            window.Livewire.hook('morphed', ({ component }) => {
                if (!ours || component?.id !== ours) {
                    return
                }

                this.restoreFocusAfterMorph()
            })
        },

        /**
         * Put focus back on the row that had it, if the morph took it away.
         *
         * ⚠️ Every write goes through Livewire, so the rows are morphed after every
         * move. morphdom replaces the focused row, focus falls to `<body>`, and a
         * keyboard user is returned to the top of the document after each reorder —
         * having to tab all the way back in to make a second one. The roving tabindex
         * still SAID a row owned the tab stop; nothing held the DOM focus.
         *
         * ⚠️ Restored ONLY when a ROW had focus and lost it. Two ways to write this
         * wrong, and both are worse than the defect: focusing on every morph would
         * yank the actor out of a row action, a modal or the search box, and focusing
         * `focusedId` unconditionally would pull focus INTO a tree the actor had never
         * entered — a host polling a notification bell would do it every thirty
         * seconds.
         */
        restoreFocusAfterMorph() {
            const wasShowing = this.confirmWasShowing
            this.confirmWasShowing = null

            // ⚠️ A confirmation that was on screen and is now GONE has been answered,
            // and it took the focused element with it. Falling through would drop the
            // actor at `<body>` — the same defect PA-14 fixed for rows, arriving by a
            // different door (F39).
            //
            // ⚠️ Conditioned on the dialog having DISAPPEARED, not merely on it having
            // been there. A morph that leaves the confirmation standing — a host's
            // poll, a notification — must not yank focus out of it.
            const answered = wasShowing !== null
                && this.$el.querySelector('[data-ltree-confirm]') === null

            const key = answered ? wasShowing : this.focusWasOnRow
            this.focusWasOnRow = null

            if (key === null) {
                return
            }

            const active = document.activeElement

            if (active instanceof Element && active !== document.body && this.$el.contains(active)) {
                // Focus stayed inside the tree — it is the actor's now, not ours.
                return
            }

            this.focusRow(this.rowFor(key))
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
            //
            // ⚠️ Asked of the dragged node's own GROUP (PA-10). It used to climb two
            // parentElements to a `.ltree-branch` wrapper, which is both a structural
            // assumption about markup and — now that the wrapper is gone — wrong. The
            // group is what actually holds the subtree, and it is already addressed by
            // key everywhere else in this controller.
            const subtree = this.childrenContainerOf(this.draggingId)

            if (subtree && subtree.contains(row)) {
                return null
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

        /**
         * ⚠️ Three states, not two (PA-8): a key the actor has opened, a key the
         * actor has closed, and a key nobody has touched — which is where the
         * host's default applies. Reading `collapsed[key] !== true` collapsed the
         * third case into "open" and left a host that starts closed unable to say
         * so; treating a missing key as CLOSED would break `toggleBranch()` the
         * other way, because reopening writes an explicit `false`.
         */
        /**
         * Is a search narrowing the tree right now?
         *
         * ⚠️ Read from `$wire`, which is REACTIVE, rather than from a data attribute:
         * a binding that read the DOM would not re-run when the search changed, so the
         * reveal would arrive a keystroke late or not at all. `treeSearch` is this
         * trait's own public property, not a private dependency on a host.
         */
        get searching() {
            return String(this.$wire?.treeSearch ?? '').trim() !== ''
        },

        /**
         * ⚠️ FOUR states, not three (PA-8, then PA-11):
         *
         *   1. a search is active    -> show everything the server left standing;
         *   2. the actor closed it   -> closed;
         *   3. the actor opened it   -> open;
         *   4. nobody has touched it -> the host's default.
         *
         * ⚠️ The search outranks the actor's own collapse, and it must. The server has
         * already REMOVED every non-matching row and kept a match's ancestors so it
         * stays reachable — so a branch left closed hides the very rows the actor
         * searched for. Before this, a match below the root was in the DOM and
         * invisible: a search that found things and showed you none of them.
         *
         * ⚠️ It REVEALS rather than expands. `collapsed` is not rewritten, so clearing
         * the box returns the tree to exactly the shape the actor had; expanding for
         * real would leave a large tree fully open after one search.
         */
        isExpanded(key) {
            if (this.searching) {
                return true
            }

            if (this.collapsed[key] === undefined) {
                return !this.startCollapsed
            }

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
            const key = row.dataset.ltreeKey

            // ⚠️ A courtesy refusal only. The REAL guard is placeNode() re-checking
            // the host's authorization on the committing call — this markup is
            // client-side and an actor can edit it.
            //
            // ⚠️ Reads `immovable`, NOT `locked` (PA-13). `locked` says the node may
            // not RECEIVE children; asking it here refused to MOVE a node merely
            // closed to new ones, and left an actor with no permission picking rows up
            // freely until the server said no at the far end of a round trip.
            if (row.dataset.ltreeImmovable === 'true') {
                this.say('refused', { name: this.nameOf(key) })

                return
            }

            const siblings = this.siblingRowsOf(row).map((r) => r.dataset.ltreeKey)

            // ⚠️ Refused BEFORE any hold begins (PA-12). A group of one has nowhere to
            // reorder to; the package used to accept the pick-up, announce "picked up,
            // 1 of 1", and say so only when an arrow key was pressed — inviting a move
            // that could not exist.
            //
            // ⚠️ Counts the siblings the actor can SEE, like everything else here. A
            // node whose only sibling is hidden from this actor IS an only child to
            // them, and behaving otherwise discloses that the hidden row exists
            // (AGENTS.md R-015).
            if (siblings.length <= 1) {
                this.say('only_child', { name: this.nameOf(key), position: 1, total: siblings.length })

                return
            }

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

        /**
         * ⚠️ There is no `total <= 1` branch here any more (PA-12). It announced
         * `only_child` on the first arrow press, and it is now unreachable: `pickUp()`
         * refuses that node before a hold exists. A branch that cannot be entered is
         * not a safety net — it is dead code that reads like one, and keeping it would
         * mean two producers of one announcement, one of them untestable.
         */
        /**
         * Every element that belongs to one row: the row, its leaf slot and its
         * children group.
         *
         * ⚠️ Since PA-10 removed the wrapper those are SIBLINGS of the row rather than
         * its descendants, so anything that moves a row has to move a block. Moving
         * the row alone would tear a subtree away from the parent it belongs to, on
         * screen, while the server still believed the old shape.
         */
        blockOf(row) {
            const block = [row]
            let next = row.nextElementSibling

            while (next && !next.hasAttribute('data-ltree-key')) {
                block.push(next)
                next = next.nextElementSibling
            }

            return block
        },

        /**
         * Put the held block at `index` among its siblings — on screen only (PA-15).
         *
         * ⚠️ The arrow keys used to announce a new position and move NOTHING. A
         * screen-reader user heard "position 1 of 3" while anyone watching the screen
         * saw the row sit still until Enter: one keystroke telling two audiences
         * different things, and no way for a sighted keyboard user to know it worked.
         *
         * ⚠️ `heldSiblings` is the group as it was at pick-up and it stays that way.
         * Moving only the held block never changes the order of the others, so their
         * captured order is still their DOM order — which is what makes this safe to
         * repeat and exact to undo.
         *
         * ⚠️ NOTHING is written here. The hold reaches the server once, at the
         * put-down (T087); a call per arrow press would file one audit row per
         * keystroke for what the actor thinks of as one move.
         */
        placeHeldAt(index) {
            const row = this.rowFor(this.heldId)

            if (!row) {
                return
            }

            const others = this.heldSiblings
                .filter((key) => key !== this.heldId)
                .map((key) => this.rowFor(key))
                .filter((candidate) => candidate !== null)

            if (others.length === 0) {
                return
            }

            const block = this.blockOf(row)

            // ⚠️ Moving a focused element blurs it, and the blur must not be mistaken
            // for the actor leaving — see `movingPreview` and `onTreeFocusOut()`.
            const hadFocus = document.activeElement === row
            this.movingPreview = true

            this.rearrange(block, others, index)

            if (hadFocus) {
                // ⚠️ Re-focused, not merely marked: the roving tabindex says which row
                // owns the tab stop, and the actor still has to BE on it to press the
                // next arrow key.
                row.focus()
            }

            this.movingPreview = false
        },

        /** @internal the DOM half of placeHeldAt(), kept separate so the guard reads clearly */
        rearrange(block, others, index) {
            if (index >= others.length) {
                const tail = this.blockOf(others[others.length - 1])
                let cursor = tail[tail.length - 1]

                for (const node of block) {
                    cursor.after(node)
                    cursor = node
                }

                return
            }

            const anchor = others[index]

            for (const node of block) {
                anchor.parentElement?.insertBefore(node, anchor)
            }
        },

        moveHeld(delta) {
            const total = this.heldSiblings.length
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

            // ⚠️ The screen and the announcement now say the same thing (PA-15).
            this.placeHeldAt(next)

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

            // ⚠️ The preview is undone, not left standing (PA-15). "Cancelled, back at
            // 3 of 3" announced over a row sitting somewhere else would be the defect
            // PA-15 fixes, inverted — the words and the screen disagreeing again.
            this.placeHeldAt(this.heldOrigin)

            this.releaseHold()
            this.say('cancelled', context)
        },

        abandonHold() {
            const context = this.heldContext({ position: this.heldOrigin + 1 })

            this.placeHeldAt(this.heldOrigin)

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

                    return
                }

                // ⚠️ Tab abandons, and does NOT consume the keystroke (PA-16).
                //
                // The tree is documented as one tab stop, but a host's row actions are
                // real focusable buttons INSIDE it — so Tab moves focus from the row to
                // its own edit button, `onTreeFocusOut()` sees focus still inside
                // `[role="tree"]`, and the node stayed held while the actor had visibly
                // left it. `KeyboardTraversalTest` counts rows with `tabindex="0"` for
                // its one-tab-stop claim, so it cannot see this.
                //
                // ⚠️ `preventDefault()` is deliberately NOT called: swallowing Tab
                // would trap a keyboard user inside the tree, which is worse than the
                // defect being fixed.
                if (event.key === 'Tab') {
                    this.abandonHold()
                }

                return
            }

            // ⚠️ PA-18. On a page with no concept of order the move bindings are not
            // registered AT ALL — not registered and then refused.
            //
            // ⚠️ `preventDefault()` is deliberately on the far side of this guard. A
            // handler that ran, held nothing and swallowed the keystroke would still be
            // taking Space away from the browser on a page that has no use for it, and
            // it would still be a binding — which is what C3 says must not exist.
            //
            // ⚠️ Space is the ONLY entrance to a hold, so guarding it here is the whole
            // guard: `heldId` can never leave `null`, and the held-key block above is
            // unreachable by construction rather than by a second check. A second check
            // would be a mechanism no single mutation could show was needed
            // (validation-log findings F1, F9, F10).
            if (event.key === ' ' || event.key === 'Spacebar') {
                if (!this.reorderEnabled) {
                    return
                }

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
            // ⚠️ Our own move's blur, not the actor's departure (PA-15).
            if (this.movingPreview) {
                return
            }

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

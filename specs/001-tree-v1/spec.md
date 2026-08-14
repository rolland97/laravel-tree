# Feature Specification: laravel-tree v1 — core placement rule and accessible Filament tree page

**Feature Branch**: `001-tree-v1`

**Created**: 2026-08-14

**Status**: Draft

**Input**: User description: "Adjacency-list tree core plus an accessible drag-and-keyboard Filament tree page"

## Context

This package is an **extraction**, not a greenfield design. Every behaviour below already
runs in production in the consumer application, where it was paid for across four merged
slices (020, 021, 029, 071). The design record, source inventory, seams and traps live in
that repository at `the consumer's package notes`.

Two consequences shape this specification:

1. **The hard requirements are already known**, because they were discovered as live defects
   rather than anticipated. They appear here as requirements with their reason attached, and
   the two most expensive are constitutional (Principles II and IV).
2. **A working test suite exists to port** — roughly 1,500 of 2,407 existing lines. The
   package starts with real coverage rather than a green README.

⚠️ **This specification must not be read as a description of code that can be copied across.**
The core action classes are close to portable; the blades are not. Every visual class in them
is a bare host-framework utility, and none of those compile from inside `vendor/`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Place a node by naming a neighbour (Priority: P1)

A developer with a hierarchy in an Eloquent model wants to re-order and re-parent nodes
correctly, including when the code requesting the move could only see part of the tree.

They call a placement action naming the node to move, its destination parent, a **reference
sibling**, and whether it goes before that sibling, after it, or last among the parent's
children. The package resolves that into a position within the *complete* destination group,
rebuilds the sibling order, and refuses moves that would create a cycle or target a node the
host considers invalid.

**Why this priority**: This is the whole reusable value. It works with no Filament, no
browser, and no UI, so it is the only story that can be delivered alone and still be worth
installing. It is also the story that carries the defect class the extraction exists to
prevent.

**Independent Test**: Fully testable with a plain Eloquent model, a database, and no
front-end of any kind — including with `filament/filament` uninstalled, which CI proves.

**Acceptance Scenarios**:

1. **Given** a parent whose children are A, B, C and an actor who can see all three,
   **When** the node D is placed *before* B, **Then** the stored order is A, D, B, C and
   every position in that group is a contiguous, collision-free sequence.
2. **Given** a parent whose children are A, B, C but where the caller could only see A and C
   because the host hid B, **When** D is placed *after* A, **Then** D is stored between A and
   B — resolved against the complete group — and B's position is not disturbed.
3. **Given** a reference sibling that is not a member of the destination group, **When** a
   placement names it, **Then** the move is refused and nothing is written.
4. **Given** a reference sibling that exists in the group but was **not** among the ones the
   caller could see, **When** a placement names it, **Then** the move is refused — a caller
   cannot have aimed at a node it was never shown.
5. **Given** a node and one of its own descendants, **When** the node is moved under that
   descendant, **Then** the move is refused as a cycle and nothing is written.
6. **Given** a destination parent the host reports as not a valid target, **When** a move
   names it, **Then** the move is refused.
7. **Given** a placement of "last child", **When** it is applied, **Then** it succeeds without
   naming any reference sibling.
8. **Given** a successful move, **When** it completes, **Then** a single domain event is fired
   describing the node, its previous parent, its new parent and its new position — and the
   package itself writes no audit record.

---

### User Story 2 - Render and drag a tree inside a Filament panel (Priority: P2)

A developer using Filament wants a working tree page in their panel without writing the tree
themselves. They extend a page class, tell it which model and which query to read, and get a
hierarchy they can expand, collapse, filter by a quick search, and rearrange by dragging.

Rows carry whatever badges and actions the host supplies. Dragging a row onto or between other
rows moves it, and where the move changes something the host has asked to confirm, the page
asks first.

**Why this priority**: It is the reason most people will install the package, and it cannot be
built before US1 because it is a caller of that rule. It delivers a usable feature on its own —
a mouse user gets a complete tree — while remaining unusable by keyboard, which US3 fixes.

**Independent Test**: Testable in a panel harness plus a browser: render a tree, expand,
collapse, search, drag a row to a new position, and confirm the stored order.

**Acceptance Scenarios**:

1. **Given** a host page declaring a model and a visible query, **When** it renders, **Then**
   the full hierarchy the query returns is displayed with parents collapsible.
2. **Given** a rendered tree, **When** a row is dragged and released beside another row,
   **Then** the resulting order matches what US1 would store for the equivalent placement —
   the page names a neighbour, never an index.
3. **Given** a quick search that removes non-matching rows, **When** a visible row is dragged,
   **Then** the move resolves against the complete group and rows hidden by the search keep
   their relative order.
4. **Given** a host that supplies row badges and row actions, **When** the tree renders,
   **Then** those appear on each row, and clicking one never starts a drag.
5. **Given** a panel with **no** custom theme, no `@source` configuration and no build step
   run by the host, **When** the page renders, **Then** the tree is fully styled in both
   light and dark.
6. **Given** a move the host has declared as needing confirmation, **When** it is requested,
   **Then** the page presents the host's warning and applies nothing until it is confirmed.

---

### User Story 3 - Traverse and describe the tree without a mouse (Priority: P3)

A keyboard or screen-reader user wants to inspect the hierarchy: reach the tree, walk it,
open and close branches, and understand where they are — which node, at what depth, and which
of how many siblings.

**Why this priority**: Before this story the tree is unusable to anyone who cannot use a
pointer, which is a WCAG failure, not a missing nicety. It is separated from US4 because
traversal is provable on its own and because reordering without traversal is meaningless.

**Independent Test**: Testable with keyboard-driven browser tests plus an automated
accessibility check, with no reordering involved.

**Acceptance Scenarios**:

1. **Given** a page containing the tree, **When** the user tabs through the page, **Then** the
   entire tree is a single tab stop.
2. **Given** focus on a row, **When** the user presses the down or up arrow, **Then** focus
   moves to the next or previous *displayed* row and does not wrap at either end.
3. **Given** focus on a collapsed parent, **When** the user presses the right arrow, **Then**
   it expands; pressing it again moves focus to the first child.
4. **Given** focus on an expanded parent, **When** the user presses the left arrow, **Then** it
   collapses; on a leaf or collapsed row, focus moves to the parent.
5. **Given** focus anywhere in the tree, **When** the user presses Home or End, **Then** focus
   moves to the first or last displayed row.
6. **Given** a row, **When** a screen reader reports it, **Then** it is announced by **its own
   name only** — not its badges, not its action buttons, and not, when expanded, the contents
   of its subtree.
7. **Given** a group where the host hides some siblings from this actor, **When** a visible row
   is reported, **Then** its position and set size describe the **rendered** siblings, so the
   existence of a hidden sibling is not disclosed.
8. **Given** the rendered page in light and in dark, **When** an automated accessibility check
   runs over it, **Then** it reports zero critical violations in both.

---

### User Story 4 - Reorder from the keyboard, with every transition announced (Priority: P4)

A keyboard user wants to move a node without a mouse. They focus a row, pick it up, move it
among its siblings, and put it down — or abandon the attempt and leave the tree unchanged.
Each transition is announced, because a user who cannot see the tree cannot see the preview.

**Why this priority**: It completes the accessibility story and closes the gap that makes the
pointer the only way to reorder. It depends on US3 for focus and on US1 for the rule, so it
cannot come earlier, and the tree is genuinely useful without it.

**Independent Test**: Testable by keyboard-driven browser tests asserting both the stored order
and the announced strings.

**Acceptance Scenarios**:

1. **Given** focus on a row the actor is allowed to move, **When** they press the pick-up key,
   **Then** the row is held and the fact that it was picked up is announced.
2. **Given** a held row, **When** the user presses up or down, **Then** the previewed position
   among its siblings changes and the new position is announced — and **nothing is persisted**.
3. **Given** a held row at a previewed position, **When** the user presses the put-down key,
   **Then** the move is committed through the same rule the pointer uses, and its completion is
   announced.
4. **Given** a held row, **When** the user cancels, **Then** the tree is unchanged and the
   cancellation is announced.
5. **Given** a held row, **When** focus leaves the tree, **Then** the hold is abandoned, the
   abandonment is announced, and nothing is persisted.
6. **Given** a held row that is an only child, or already first, or already last, **When** the
   user attempts to move it further, **Then** that condition is announced and nothing changes.
7. **Given** a row the actor is not allowed to move, **When** they press the pick-up key,
   **Then** the refusal is announced and no hold begins.
8. **Given** a completed keyboard move, **When** the audit record is inspected, **Then**
   exactly **one** move event was fired for the whole interaction, not one per keystroke.

---

### Edge Cases

- **A reference sibling disappears between render and drop** — another actor moved or deleted
  it. The move must be refused rather than resolved to a neighbouring row.
- **Legacy data holds duplicate or sparse positions.** The read order must remain deterministic,
  and any move that touches a group must leave that group contiguous and collision-free.
- **A group contains exactly one node.** Every reorder request is a no-op that must be reported,
  not silently accepted.
- **The host's visible query returns a node whose parent it does not return** — an orphan from
  the actor's perspective. It must render at the root of what the actor can see without
  implying its true parent.
- **A search is active and matches nothing.** The tree must state that rather than appear empty
  and broken.
- **A node is dropped onto itself, or onto its own descendant.** Refused as a cycle.
- **Two moves race on the same group.** The second must resolve against the state left by the
  first, not against what its browser drew.
- **The host application does not have Filament installed.** Everything in US1 must work, and
  nothing in the package may fail to load.
- **The host's panel uses a custom theme, or none at all.** The tree must look the same either
  way, because it brings its own styling.
- **A screen reader user holds a node when the surrounding page re-renders itself** — for
  example a notification poll. The hold must survive, or be abandoned with an announcement;
  it must never be silently dropped.

## Requirements *(mandatory)*

### Functional Requirements

**The placement rule (US1)**

- **FR-001**: The package MUST expose a placement request as a reference sibling plus a
  placement of *before*, *after*, or *last child*.
- **FR-002**: The package MUST NOT accept a caller-supplied numeric position index in any
  public entry point.
- **FR-003**: The package MUST resolve a placement against the **complete** destination group
  while accepting, as a separate argument, the set of siblings the caller could see.
- **FR-004**: The package MUST refuse a placement whose reference sibling is not a member of
  the complete destination group.
- **FR-005**: The package MUST refuse a placement whose reference sibling was not among the
  siblings the caller could see.
- **FR-006**: The package MUST refuse a placement whose reference sibling is the node being
  moved.
- **FR-007**: A *last child* placement MUST NOT require a reference sibling.
- **FR-008**: The package MUST refuse any move that would make a node a descendant of itself.
- **FR-009**: The package MUST ask the host whether a destination parent is a valid target, and
  refuse the move when it is not.
- **FR-010**: After any move, every node in the affected group MUST hold a contiguous,
  collision-free sequence of positions.
- **FR-011**: The read order of a group MUST be deterministic and MUST be configurable, so a
  host whose data ties on position resolves it the same way the package does.
- **FR-012**: The names of the parent and position attributes MUST be configurable.
- **FR-013**: Each refusal MUST be distinguishable by type, so a host can react to a cycle
  differently from an invalid reference.
- **FR-014**: A successful move MUST fire exactly one domain event carrying the node, its
  previous parent, its new parent and its new position.
- **FR-015**: The package MUST NOT write an audit or activity record of its own.
- **FR-016**: A move and its sibling renumbering MUST be atomic — a failure part-way MUST leave
  the previous order intact.

**Host boundaries (all stories)**

- **FR-017**: The package MUST NOT decide which nodes an actor may see; it MUST accept that
  decision from the host.
- **FR-018**: The package MUST NOT ship a migration that runs automatically. Any schema change
  MUST be published by the host before it runs.
- **FR-019**: All copy the package emits MUST be overridable by the host without modifying the
  package.
- **FR-020**: The package's core MUST NOT require a Filament installation, and MUST NOT fail to
  load when Filament is absent.

**The Filament tree page (US2)**

- **FR-021**: The package MUST provide a page a host extends by naming a model and a query.
- **FR-022**: The page MUST let the host contribute per-row badges, per-row actions, header
  actions and a leaf slot, without the host editing package markup.
- **FR-023**: Dragging MUST be initiated only from a dedicated handle, so activating a row
  action never begins a drag.
- **FR-024**: The page MUST support expanding and collapsing branches, and a quick search that
  narrows the displayed rows.
- **FR-025**: A drop MUST be expressed to the rule as a reference sibling and a placement.
- **FR-026**: The page MUST support a host-defined confirmation step that shows the host's own
  warning text and applies nothing until confirmed.
- **FR-027**: The page MUST re-check the host's authorization on the single committing call,
  regardless of any check made earlier for presentation.
- **FR-028**: The package MUST supply its own compiled styling, registered so that it loads
  without the host running a build step, editing a theme, or configuring a content glob.
- **FR-029**: Package markup MUST NOT rely on host-framework utility classes for its appearance.
- **FR-030**: The tree MUST render correctly in both light and dark, inheriting the host panel's
  accent colour rather than defining its own.

**Keyboard and assistive technology (US3, US4)**

- **FR-031**: The whole tree MUST be a single tab stop, with one row focusable at a time.
- **FR-032**: Arrow keys MUST move focus between displayed rows without wrapping at either end.
- **FR-033**: Right and left arrows MUST expand and collapse, and then descend and ascend.
- **FR-034**: Home and End MUST move focus to the first and last displayed rows.
- **FR-035**: The role, the focusability and every accessibility property of a row MUST be on
  the same element.
- **FR-036**: Each row MUST be announced by its own name only, excluding its badges, its
  actions, and its subtree.
- **FR-037**: A row's reported position and set size MUST describe the **rendered** siblings,
  never the true group size.
- **FR-038**: Controls that duplicate a state already announced on the row MUST be hidden from
  assistive technology rather than given a label.
- **FR-039**: A held node MUST be movable among its siblings with each previewed position
  announced, and MUST persist nothing until it is put down.
- **FR-040**: Picking up, moving, putting down, cancelling, abandoning, refusing, and the
  boundary conditions (only child, already first, already last) MUST each be announced.
- **FR-041**: A completed keyboard interaction MUST produce exactly one move event.
- **FR-042**: A keyboard move MUST commit through the same code path as a pointer move.
- **FR-043**: An announcement MUST survive a re-render of the surrounding page that the package
  did not initiate.
- **FR-044**: A refusal to pick up MUST be announced before any hold begins.

**Proving it (all stories)**

- **FR-045**: The test suite MUST include a second, unrelated model so no behaviour is proven
  against a single consumer.
- **FR-046**: Continuous integration MUST run the core suite with Filament uninstalled.
- **FR-047**: Assertions about ordering MUST read the stored rows rather than the rendered
  output.
- **FR-048**: Fixtures used to prove an ordering rule MUST be named so that a correct and an
  incorrect implementation produce **different** output.

### Key Entities

- **Tree node**: Any host model that participates in the hierarchy. It knows its parent, its
  position among siblings, and whether it may be used as a destination. Everything else about
  it — its name, its permissions, its lifecycle — belongs to the host.
- **Sibling placement**: The intent of a move relative to a neighbour — before it, after it, or
  last among a parent's children. The only vocabulary in which a move may be expressed.
- **Destination group**: All children of a destination parent, excluding the node being moved.
  ⚠️ Distinct from the **rendered** siblings, which is the subset the actor could see. Conflating
  the two is the defect this package exists to prevent.
- **Move event**: The record that a node changed place, emitted for the host to do with as it
  wishes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer can install the package, add the trait to a model, publish and run
  the schema stub, and reorder nodes correctly from plain code, without installing Filament.
- **SC-002**: A move requested against a partially visible tree produces the same stored order
  as the identical move requested against the fully visible tree — **100% of cases**, including
  when the hidden siblings sit between the visible ones.
- **SC-003**: After any sequence of moves, every affected group holds positions that are
  contiguous and free of duplicates — verified by reading stored rows, not rendered output.
- **SC-004**: Every refusal path (cycle, invalid target, unknown reference, unseen reference,
  self-reference) is individually distinguishable, and each is proven by a test observed
  failing before its guard existed.
- **SC-005**: A developer can add the tree page to a panel that has no custom theme and no build
  step of its own, and the tree renders fully styled in both light and dark.
- **SC-006**: The entire tree is reachable and operable using only a keyboard — traverse,
  expand, collapse, jump to ends, and complete a reorder — with **zero** mouse input.
- **SC-007**: An automated accessibility check reports zero critical violations on the rendered
  tree in both light and dark.
- **SC-008**: A screen-reader user can determine, for any focused row, which node it is, how
  deep it is, and which of how many siblings it is — where the sibling count reflects only what
  they are permitted to see.
- **SC-009**: A complete keyboard reorder produces exactly one move event, regardless of how
  many keystrokes it took.
- **SC-010**: The core suite passes with Filament uninstalled, and the full behaviour is proven
  against **two** unrelated models.

⚠️ **SC-011 is stated separately because it cannot be met by any automated check.** Every
announcement in US4, and the row naming in US3, MUST be walked with a **real screen reader** by
a person who can hear the output, and recorded as heard sentences. An accessibility-tree dump
and an automated check both prove that the *data* is present and that a name *exists*; neither
proves the announcements make sense in sequence. Until that walk happens, SC-011 MUST be
reported as **unproved** rather than inferred from SC-007 or SC-008.

**This is not a hypothetical caution.** The source application shipped this exact tree with
four *correct* accessibility assertions passing while the announced name was wrong, because
every property was asserted except the one a screen reader leads with. It also has two such
walks outstanding today for want of a machine with a screen reader and working audio, so this
criterion is likely to be the last one closed.

## Out of Scope

Deliberately excluded, because they are the host's product rather than tree mechanics: merging
two nodes, bulk-assigning records into a node, editing a node's own attributes, retiring or
restoring a node, showing the records that belong to a node, and any panel for records that
belong to no node. A host builds these as its own actions and contributes them through the row
and header slots.

Also out of scope for v1: nested-set or materialized-path storage, drag-and-drop between two
separate trees, multi-select moves, and undo.

## Assumptions

- The host stores its hierarchy as an **adjacency list** — each node holds a reference to its
  parent — and is willing to add an integer ordering attribute if it lacks one.
- The host already has, or is willing to write, its own answer to *who may see this node*. The
  package will not supply a default, because a default here fails in the unsafe direction.
- Hosts using the Filament bridge are on Filament v5. Hosts using only the core are on a
  supported Laravel version and need nothing else.
- A host that wants an audit trail already has one. The package emits an event and assumes the
  host knows what to do with it.
- The extraction is faithful rather than exploratory: where this specification and the running
  behaviour in the source application disagree, the running behaviour is presumed right and the
  disagreement is a finding to investigate — it has been wrong before.
- ⚠️ **The first consumer is the source application itself**, adopted through a local path
  reference before anything is published. No version is tagged until that adoption is green,
  because a published version cannot be retracted and an API frozen without a consumer is
  frozen against guesses.

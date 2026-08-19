---

description: "Task list for laravel-tree v1"
---

# Tasks: laravel-tree v1 — core placement rule and accessible Filament tree page

**Input**: Design documents from `/specs/001-tree-v1/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/public-api.md](contracts/public-api.md),
[quickstart.md](quickstart.md)

## ⚠️ Two template defaults are overridden for this feature

1. **Tests are MANDATORY, not optional.** The shared template says tests are optional; the
   constitution's Principle I says every behaviour change lands with the test that would have
   caught its absence, **observed failing first**. The constitution governs
   (`.specify/memory/constitution.md` § Governance). This was flagged in its own sync impact
   report as a per-feature resolution rather than a template edit.
2. **User stories MUST NOT be worked in parallel.** The template says stories can proceed in
   parallel once foundational work is done. **For this feature they cannot**, and the reason is
   evidentiary rather than technical — see the note under Phase 4.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel — different files, no dependencies.
- **[Story]**: US1–US4, or `SETUP`/`FOUND`/`SPIKE`/`POLISH`.
- Every task names its real path.

## Path conventions

Single package at repository root: `src/`, `resources/`, `config/`, `database/`, `tests/`,
per [plan.md](plan.md) § Project Structure.

---

## Phase 1: Setup — package skeleton and pipeline

**Purpose**: A repository that builds, lints, analyses and runs three empty suites green.

- [X] T001 [SETUP] Create `composer.json`: name `rolland97/laravel-tree`, PHP `^8.3`,
      `illuminate/database` + `illuminate/support` ~~`^11.0|^12.0|^13.0`~~ **`^13.0`**,
      `staudenmeir/laravel-adjacency-list` `^1.26`. ⚠️ The pairing in this task was
      unsatisfiable — `^1.26` requires illuminate `^13.0` ONLY. Laravel 11 is uninstallable and
      Laravel 12 has no consumer; see constitution 1.1.0 and 1.2.0. ⚠️ `filament/filament ^5.0` goes in
      `require-dev` and `suggest` **only** — never `require` (`AGENTS.md` R-001).
- [X] T002 [SETUP] PSR-4 autoload: `Rolland\Tree\` → `src/`, `Rolland\Tree\Tests\` → `tests/`.
- [X] T003 [P] [SETUP] `pint.json` (laravel preset). ⚠️ Re-cite `AGENTS.md` R-032 against this
      file — it is currently tagged `[pending artifact]`.
- [X] T004 [P] [SETUP] `phpstan.neon.dist` over `src` and `config`. ⚠️ Re-cite `AGENTS.md` R-031.
- [X] T005 [P] [SETUP] `phpunit.xml.dist` with three suites — `core`, `bridge`, `browser` —
      `executionOrder="random"`, `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite`,
      `beStrictAboutOutputDuringTests`. ⚠️ Re-cite `AGENTS.md` R-029.
- [X] T006 [SETUP] Orchestra Testbench + Pest 4 in `require-dev`; `tests/TestCase.php` and
      `tests/Pest.php` applying it directory-wide.
- [X] T007 [SETUP] `src/TreeServiceProvider.php` — publishes config, translations and the
      migration stub; conditionally registers the bridge provider when `Filament\Panel` exists.
- [X] T008 [P] [SETUP] `.github/workflows/tests.yml` — matrix across PHP 8.3/8.4 × Laravel
      11/12/13 × `prefer-lowest`/`prefer-stable`. Actions pinned to full commit SHAs with a
      version comment, explicit least-privilege `permissions:`. ⚠️ Re-cite `AGENTS.md` R-033.
- [X] T009 [SETUP] **CI job `core-without-filament`**: `composer remove --dev filament/filament`
      then run the `core` suite. Research R8 — an arch test is not a substitute, because it
      proves nothing was imported rather than that the package boots.
- [X] T010 [P] [SETUP] `.gitattributes` with `export-ignore` for `specs/`, `tests/`, `.specify/`,
      `.claude/`, `resources/css/`, `resources/js/`, `.github/`. Constitution Principle VI.
- [X] T011 [P] [SETUP] `README.md`. ⚠️ It MUST NOT instruct hosts to add an `@source` glob or run
      a bundler (`AGENTS.md` R-019/R-020), and MUST NOT claim "no commands at all" — state the
      qualified form from research R6.

**Checkpoint**: `composer test`, `composer analyse`, `composer test:lint` all green on empty suites.

---

## Phase 2: Foundational — the vocabulary every story needs

**⚠️ BLOCKING**: no user story starts until this phase completes.

- [X] T012 [P] [FOUND] `config/tree.php` — `parent_column`, `position_column`, `tiebreaker`
      (data-model.md § Configuration).
- [X] T013 [P] [FOUND] `src/Contracts/TreeNode.php` exactly as `contracts/public-api.md` states.
      ⚠️ **No visibility member.** Visibility is a property of a node *and an actor*; putting it
      here invites a global default, and a default fails in the unsafe direction.
- [X] T014 [FOUND] `src/Concerns/IsTreeNode.php` — `treeParentId()`, `treePosition()`, recursive
      relationships. ⚠️ Deliberately does **not** implement `isValidTreeTarget()`: a default there
      is the package deciding, and every host that forgot would silently inherit it.
- [X] T015 [P] [FOUND] `src/Enums/SiblingPlacement.php` with `needsReference()`.
- [X] T016 [P] [FOUND] `src/Exceptions/{CycleException,InvalidTargetException,UnreachableReferenceException}.php`.
- [X] T017 [P] [FOUND] `src/Events/{NodeMoved,SiblingsReordered}.php` per contracts.
- [X] T018 [P] [FOUND] `database/migrations/add_tree_columns.php.stub`. ⚠️ A **stub**, not an
      auto-discovered migration (`AGENTS.md` R-002).
- [X] T019 [P] [FOUND] `lang/en/tree.php` — refusal messages and announcement templates,
      publishable.
- [X] T020 [FOUND] Fixture model `tests/Fixtures/Category.php` (mirrors the source application).
- [X] T021 [FOUND] Fixture model `tests/Fixtures/Page.php` — **unrelated to Category**, different
      column names, different tiebreaker. Research R9: one consumer proves nothing, and a single
      fixture lets every configurable seam be accidentally hard-coded and still pass.

**Checkpoint**: vocabulary exists; US1 can begin.

---

## Phase 3: User Story 1 — Place a node by naming a neighbour (P1) 🎯 MVP

**Goal**: The placement rule, correct even when the caller saw only part of the tree.

**Independent Test**: Whole story provable with `filament/filament` uninstalled.

### Tests first — each watched failing (`AGENTS.md` R-023)

- [X] T022 [P] [US1] `tests/Core/ResolveSiblingPlacementTest.php`: `Before`/`After` against a
      fully-visible group. ⚠️ **Name fixtures so a right and a wrong implementation differ** —
      the read tie-breaks by name, so a careless fixture passes either way (`AGENTS.md` R-025).
- [X] T023 [P] [US1] Partial-visibility case: a hidden sibling sits **between** two visible ones;
      the resolved index must fall in the complete group. Use `Aardvark` for the hidden sibling
      (quickstart.md § SC-002).
- [X] T024 [P] [US1] Refusal: reference not a member of the complete group.
- [X] T025 [P] [US1] Refusal: reference in the group but **not** in `renderedSiblingIds`.
- [X] T026 [P] [US1] Refusal: reference is the node itself.
- [X] T027 [P] [US1] `LastChild` resolves with **no** reference supplied.
- [X] T028 [P] [US1] `tests/Core/MoveNodeTest.php`: cycle refusal (destination is a descendant).
- [X] T029 [P] [US1] Invalid-target refusal via `isValidTreeTarget()` answering false.
- [X] T030 [P] [US1] `tests/Core/OrderIntegrityTest.php`: after any move the affected group holds
      a contiguous, collision-free sequence. ⚠️ **Its own `it()` case, never appended to an
      ordering assertion with `->and()`** — a chain stops at the first failure, so it could never
      be watched failing on its own (quickstart.md § SC-002/SC-003).
- [X] T031 [P] [US1] Atomicity: a failure mid-renumber leaves the previous order intact.
- [X] T032 [P] [US1] `NodeMoved` fires **exactly once** per move; `SiblingsReordered` once per
      reorder; **no** audit record is written by the package.
- [X] T033 [P] [US1] Numeric-string keys: ids arriving from the driver as strings still match
      their own group. Research R4 — this was found by static analysis, not by a test, and a
      strict comparison would silently refuse a valid reference.
- [X] T034 [P] [US1] Every core test above also runs against the `Page` fixture (FR-045).
- [X] T035 [US1] **Watch T022–T034 fail.** Record each RED message in
      `checklists/validation-log.md`. ⚠️ **If any test cannot be made to fail, that is the
      finding** — investigate before proceeding (`AGENTS.md` R-023).

### Implementation

- [X] T036 [US1] `src/Actions/ResolveSiblingPlacement.php` — resolve against the complete group,
      refuse unreachable references. Cast plucked keys to `int` and compare strictly (R4).
- [X] T037 [US1] `src/Actions/MoveNode.php` — cycle guard, target guard, atomic renumber, fires
      `NodeMoved`. No activity-log call (research R5).
- [X] T038 [US1] `src/Actions/ReorderSiblings.php` — same-parent case, fires `SiblingsReordered`.
- [X] T039 [US1] `src/Actions/PlaceNode.php` — the composed entry point hosts call.
      ⚠️ **Named `PlaceNode`, never `DropNode`.** If this signature ever changes, **rename it**:
      a renamed method throws before dispatch, whereas named arguments are silently discarded by
      the container's method injection (`AGENTS.md` R-030).
- [X] T040 [US1] ⚠️ **Guard the one way the 071 defect can return.** `MoveNode` takes an integer
      position, and today only a doc comment separates it from `PlaceNode`. Add a test asserting
      the public surface offers **no** index-taking entry point, and make `MoveNode`'s role
      explicit in its own docblock. Raised by the post-design constitution re-check (plan.md).
- [X] T041 [US1] `renderedSiblingIds` has **no default value** anywhere in the surface. An empty
      default silently turns the visibility refusal into a no-op.
- [X] T042 [US1] Pint + PHPStan clean; all of T022–T034 green.

**Checkpoint**: US1 shippable. ⚠️ **Ship it before starting US2** — see below.

---

## Phase 4: R10 spike — can a package serve a panel to a browser? (BLOCKING for US2–US4)

**⚠️ This phase exists because research R10 is `[open]`, and three of the four stories depend on
it.** The source application's browser tests run against a full Laravel application; that is
**not** evidence the same works from inside a package.

- [X] T043 [SPIKE] Stand up the smallest possible Testbench panel with one trivial tree page.
- [X] T044 [SPIKE] Drive it with a browser driver: load the page, assert one element.
- [X] T045 [SPIKE] Run axe against it and get a result — in **both** colour schemes.
- [X] T046 [SPIKE] Confirm the panel serves **compiled CSS**. ⚠️ If it does not, every styling
      assertion in US2–US4 is vacuous and the plan needs revising, not working around.
- [X] T047 [SPIKE] Record the outcome in `research.md` R10, changing its tag from `[open]` to
      `[verified]` **or** to a named limitation. ⚠️ **Do not leave it `[open]` and proceed** —
      "prove fragile seams first"; discovering the harness cannot serve a panel after the page
      exists converts a spike into a rewrite.

**Checkpoint**: R10 answered. Only now does US2 start.

⚠️ **Why the stories are strictly sequential here, against the template's default.** US1 must
ship with the pointer as the placement rule's **only** caller. Once a keyboard caller exists, no
test result can say which of the two made the rule pass, and that evidence is not reproducible
later. The source application shipped US1 alone for exactly this reason (an internal merge request, with no keyboard
code at all). Parallelising the stories destroys the proof.

---

## Phase 5: User Story 2 — Render and drag a tree in a Filament panel (P2)

### Tests first

- [X] T048 [P] [US2] `tests/Bridge/TreePageTest.php`: a host page declaring model + visible query
      renders the full hierarchy.
- [X] T049 [P] [US2] Host-supplied badges, row actions, header actions and leaf slot all appear.
- [X] T050 [P] [US2] Quick search narrows displayed rows.
- [X] T051 [P] [US2] Confirmation flow: nothing is applied until confirmed.
- [X] T052 [P] [US2] `placeNode()` re-checks authorization **on the committing call**, and an
      unauthorised call changes nothing.
- [X] T053 [P] [US2] `tests/Browser/DragTest.php`: drag reorder and drag nest produce the same
      stored order as the equivalent `PlaceNode` call. ⚠️ Drag **after** rather than before —
      a before-drop is indistinguishable between a right and wrong implementation.
- [X] T054 [P] [US2] Drag with a search active: hidden rows keep their relative order.
- [X] T055 [US2] **Watch T048–T054 fail**; record REDs.

### Implementation

- [X] T056 [US2] `src/Filament/Pages/TreePage.php` — `nodesByParent()`, `searchVisibleIds()`,
      `placeNode()`, `confirmPendingMove()`, `cancelPendingMove()`, and the host hooks.
- [X] T057 [US2] `src/Filament/FilamentTreeServiceProvider.php` — registers assets via
      `FilamentAsset::register([...], package: 'rolland97/laravel-tree')`.
- [X] T058 [US2] `resources/views/tree.blade.php` and `tree-branch.blade.php`.
      ⚠️ **Written from scratch against package classes, NOT copied.** Every visual class in the
      source blades is a bare host utility that will not compile from `vendor/` (`AGENTS.md`
      R-020).
- [X] T059 [US2] `resources/css/tree.css` — `ltree-` prefixed classes; `<x-filament::*>` for
      anything Filament covers; inherit `var(--primary-*)` rather than defining a palette.
- [X] T060 [US2] Build pipeline (esbuild per research R6) producing `resources/dist/tree.css`.
      **Commit the output.**
- [X] T061 [US2] Drag controller: pointer-position based, initiated only from a dedicated handle
      so a row action never starts a drag (FR-023).
- [X] T062 [US2] ⚠️ **Verify styling live, in a real browser, light AND dark.** No suite assertion
      can prove this — the harness serves no compiled CSS (quickstart.md § SC-005). Check the
      stylesheet, not just the page: a bare-`<div>` probe reading `outline-style` cannot fail,
      because its default is already `none`.

**Checkpoint**: pointer users have a complete tree. Keyboard users still have nothing.

---

## Phase 6: User Story 3 — Traverse and describe the tree without a mouse (P3)

### Tests first

- [X] T063 [P] [US3] `tests/Browser/KeyboardTraversalTest.php`: the whole tree is **one** tab stop
      (count `tabindex="0"` — it must stay at 1 throughout).
- [X] T064 [P] [US3] Arrows move between displayed rows and **do not wrap** at either end.
- [X] T065 [P] [US3] Right expands then descends; Left collapses then ascends; Home/End jump.
- [X] T066 [P] [US3] ⚠️ **Assert the announced NAME of a row directly** — its own name only, not
      its badges, not its action labels, and not its subtree when expanded. This is the assertion
      the source application omitted while shipping four *correct* `aria-*` assertions
      (`AGENTS.md` R-014).
- [X] T067 [P] [US3] Position and set size count **rendered** siblings. Scope the guard to a
      search-filtered state so it **can** fail — the equivalent guard in the source application
      could not, because privacy is filtered upstream of the count.
- [X] T068 [P] [US3] Role, `tabindex` and every `aria-*` sit on the **same** element.
- [X] T069 [P] [US3] axe reports zero criticals in light **and** dark.
- [X] T070 [US3] **Watch T063–T069 fail**; record REDs. ⚠️ **T067 is the one to distrust** — if it
      passes immediately, the guard is measuring something downstream of the real guarantee.

### Implementation

- [X] T071 [US3] Roving tabindex; one focusable row at a time.
- [X] T072 [US3] `onTreeKeydown` / `onTreeFocusOut` traversal in `resources/js/tree.js`.
- [X] T073 [US3] ARIA on the treeitem: level, position, set size, expanded, and
      `aria-labelledby` pointing at the name span **alone**.
- [X] T074 [US3] Chevron decorative to assistive technology (`aria-hidden`, `tabindex="-1"`)
      rather than labelled — the row already announces the state (FR-038).
- [X] T075 [US3] Focus ring via package CSS. ⚠️ `outline-none` zeroes `outline-style` while a
      width utility only sets width — the ring then has width and colour but no style. Verify the
      computed value live in both schemes.
- [X] T076 [US3] ⚠️ If the page uses any collapsible section, check its collapse button has an
      accessible name. Filament's own component ships `aria-label=""`, which is a critical
      violation the package would inherit (PKG-01 § Traps).

**Checkpoint**: the tree is inspectable by keyboard and screen reader. Reordering still is not.

---

## Phase 7: User Story 4 — Reorder from the keyboard, with announcements (P4)

### Tests first

- [X] T077 [P] [US4] `tests/Browser/KeyboardReorderTest.php`: pick up → announced.
- [X] T078 [P] [US4] Move among siblings → each new position announced, **nothing persisted**.
- [X] T079 [P] [US4] Put down → committed through the same path as the pointer, completion
      announced. ⚠️ The source application shipped `put_down` with **no assertion at all** and
      only its critique caught it.
- [X] T080 [P] [US4] Cancel → tree unchanged, cancellation announced.
- [X] T081 [P] [US4] Focus leaves the tree mid-hold → abandonment announced, nothing persisted.
- [X] T082 [P] [US4] Boundaries announced: only child, already first, **already last** — the
      second omission the critique found.
- [X] T083 [P] [US4] Unauthorised pick-up → refusal announced, no hold begins.
- [X] T084 [P] [US4] A completed interaction fires **exactly one** move event, not one per
      keystroke.
- [X] T085 [P] [US4] The announcement survives a re-render the package did not initiate.
- [X] T086 [US4] **Watch T077–T085 fail**; record REDs.

### Implementation

- [X] T087 [US4] Held state lives client-side; **no server call until the drop**. A call per arrow
      press would write one audit row per keystroke for what the user thinks of as one move.
- [X] T088 [US4] Commit through the **same** helper the pointer release uses — exactly one copy of
      the placement contract.
- [X] T089 [US4] Live region excluded from morphing. ⚠️ Its content is **client state the server
      knows nothing about**; a re-render fires more than one morph and the second wipes what the
      first wrote.
- [X] T090 [US4] Morph hook checks **which** component morphed. ⚠️ Without it a host panel polling
      a bell every 30 seconds silently abandons every held node. And scoping by component id does
      **not** work — dropping the check rather than fixing it is how that defect got in.
- [X] T091 [US4] `focus()` after `$nextTick`. ⚠️ `focus()` on a hidden element is a silent no-op,
      and at morph time the moved row's branch may still be `display: none`.
- [X] T092 [US4] ⚠️ Browser-harness traps (research R11): follow every expand with a waiting
      assertion, because `keys()` is focus-then-type and a key pressed mid-expand lands on the
      previously focused row. And `void` any `$wire.$refresh()` — awaited in a page evaluation it
      returns a promise that never resolves, and hung the source application's run for fifteen
      minutes.

**Checkpoint**: all four stories functional.

---

## Phase 8: Polish, verification and the release gate

- [X] T093 [P] [POLISH] Re-cite the ~~five~~ **four** `[pending artifact]` rules in `AGENTS.md`
      (R-029, R-031, R-032, R-033 — the count in this task was wrong) against the
      files T003–T005/T008 created, retagging each `[ratified]`. ⚠️ A rule still citing an
      intention after its artifact exists is drift.
- [X] T094 [P] [POLISH] Update the constitution's sync impact report: `README.md` now exists.
- [X] T095 [POLISH] Run every procedure in `quickstart.md` § Verification.
- [X] T096 [POLISH] `/speckit-analyze` for cross-artifact consistency.
- [X] T097 [POLISH] `/speckit-superb-critique`. ⚠️ **Run this before the branch is finished, not
      only at the end** — on the last comparable slice it found three gaps that eleven guards and
      2,313 passing tests did not, every one between the spec's words and what shipped.
- [X] T098 [POLISH] Confirm the distribution archive carries runtime only (quickstart.md § Release
      gate).
- [ ] T099 [POLISH] ⚠️ **SC-011 screen-reader walk** — the ten steps in `quickstart.md`. Needs a
      machine with a real screen reader and **working audio**. **If no such machine is available,
      leave this task OPEN and report SC-011 as UNPROVED.** Do not close it with an accessibility
      tree dump or an axe pass; neither proves announcements work as heard sentences.

      ⚠️ **DEFERRED TO THE NEXT RELEASE by owner decision, 2026-08-18.** The screen-reader
      walk is deliberately out of scope for this release, in this package **and** in the
      consumer application (its SC-007 is deferred on the same decision). This task stays
      **OPEN**, because deferring a proof is not obtaining one: SC-011 remains **UNPROVED**
      and must not be reported otherwise. What changed is only that it no longer blocks a
      release — it is scheduled work, not an omission.
- [X] T100 [POLISH] ✅ **DONE 2026-08-18 — released at `v0.9.0`, repository public.** The gate
      was met in the order it required: the consumer application adopted this package, its full
      suite passed **in CI on a clean checkout** (MR !121, pipeline 586 green on all three jobs),
      and its existing audit tests passed **unchanged** — the strongest signal the event seam held.
      Only then was the tag cut (`AGENTS.md` R-035).

      ⚠️ **0.9.0, not 1.0.0, deliberately.** That single adoption cost **seventeen** public-API
      amendments (PA-1…PA-17), two of them on the final day, and the consumer's next slice will use
      the tree a different way again. 0.x keeps the freedom to keep amending without a major bump
      each time; 1.0.0 follows when the surface stops moving.

      ⚠️ **R-036 verified rather than assumed**: `git archive v0.9.0 | tar -t` ships only
      `LICENSE`, `README.md`, `composer.json`, `config/`, `database/`, `lang/`, `resources/` and
      `src/` — no specs, tests, agent configuration or build sources.

      ⚠️ **Publishing needed more than archive hygiene.** The git history and the commit messages
      named the consumer 152 times, and force-pushed objects survive on GitHub — measured, by
      fetching one back after a force-push. The repository was **deleted and recreated**, then
      verified with an anonymous clone. Archive hygiene and disclosure hygiene are different
      problems, and R-036 only covers the first.

---

## Phase 9: PA-18 — ordering as a page-level slot (after v0.9.0)

⚠️ Raised by the SECOND consumer slice (074, a Drive-style folder browser), which is blocked
until this merges. Contract: `contracts/package-amendment-pa18.md` in that repository, C1–C8
binding. ⚠️ **No tag is cut for this** — the consumer tracks `dev-001-tree-v1` on purpose, and
the surface is still moving (`AGENTS.md` R-035).

- [X] T101 [PA-18] Twin host fixtures: `OrderedTwinTreePage` and `UnorderedTreePage`, the second
      EXTENDING the first and overriding `treeReorderEnabled()`, the slug and the title — nothing
      else. ⚠️ C5–C8 claim things are *unchanged*, and the only honest way to assert that is to
      diff two pages that differ in nothing else. Two independently written fixtures could drift
      into a difference the diff would then blame on the slot.
      ⚠️ `canMoveNode()` is deliberately left at its default `true`, so any refusal wording
      reaching the live region can only have come from the wrong slot.
- [X] T102 [PA-18] Watch C1/C2 fail — the handle and `draggable="true"` render on a host that has
      retired ordering.
- [X] T103 [PA-18] Watch C3/C4 fail — Space picks a node up and announces *"Picked up Charlie…"*
      on that same host. ⚠️ Note the wording of the red: it is a PICK-UP, not a permission
      refusal. A refusal here would have meant `canMoveNode()` was doing the work and the R1
      defect had been reproduced rather than fixed.
- [X] T104 [PA-18] `treeReorderEnabled(): bool` on `InteractsWithTree`, **defaulting to true**.
      ⚠️ The default cannot be false: every existing host already has ordering.
- [X] T105 [PA-18] Gate the handle in `tree-branch.blade.php` and pass the answer to the Alpine
      controller as `x-data="ltree(…, …, @js($this->treeReorderEnabled()))"`; guard the Space
      binding in `resources/js/tree.js`. ⚠️ `npm run build` — `resources/dist/` is COMMITTED and a
      stale bundle ships broken code every local check calls green.
- [X] T106 [PA-18] Prove the C5–C8 *preservation* guards discriminate, since none of them has ever
      been red. One mutation each: drop `aria-posinset`/`aria-setsize` (C5), drop the chevron (C6),
      drop the search toolbar (C7), flip the default to false (C8). Recorded in the validation log.
- [X] T107 [PA-18] ⚠️ Check whether the blade guard and the controller guard are the two-mechanism
      shape findings F1/F9/F10 describe, by mutating **each alone**. They are not — they cover
      different surfaces and each reddens guards the other leaves green.
- [X] T108 [PA-18] Document PA-18 beside PA-1…PA-17: `contracts/public-api.md` member table and
      amendment section, and a README section separating it from `canMoveNode()`.

**Checkpoint**: `composer test`, `composer analyse`, `composer test:lint` green; 074 unblocked.

---

## Phase 10: PA-19 — the tree as content, so a host can compose it (after v0.9.0)

⚠️ Raised by the SAME consumer slice (074) once PA-18 landed: its page needs **three
regions** (FR-001) and the tree could only be a whole page. Contract:
`contracts/package-amendment-pa19.md` in that repository, C1–C7 binding. ⚠️ **Still no tag.**

- [X] T109 [PA-19] Composed host fixtures: `ComposedTreePage` (own view, includes the
      partial) and `ComposedUnorderedTreePage` (composed **and** PA-18 off). ⚠️ The second
      exists because the two amendments must hold together and only a composed-and-unordered
      host can catch a dropped `x-data` argument.
- [X] T110 [PA-19] Watch C1 and C3–C7 fail — `View [tree-content] not found`, twelve cases.
- [X] T111 [PA-19] Split `tree.blade.php`: everything inside the page component moves to
      `tree-content.blade.php`, and the page view becomes a wrapper that includes it.
      ⚠️ **Blade only** — `resources/js/` and `resources/css/` are untouched, so no rebuild.
- [X] T112 [PA-19] ⚠️ **C2 is proven by NOT touching the existing suites.** 358 existing
      cases pass unchanged; its value is that it never went red.
- [X] T113 [PA-19] Prove each new guard discriminates. ⚠️ Two were written wrong and are
      written up in the validation log: a comparison whose two sides shared the mutated
      component, and a bridge assertion blind to a controller argument.
- [X] T114 [PA-19] Document `tree::tree-content` as public in `contracts/public-api.md` and
      `README.md`, and say plainly that `tree-branch` is not.

**Checkpoint**: `composer test`, `composer analyse`, `composer test:lint` green; 074's
three-region layout unblocked.

---

## Dependencies & execution order

### Phase dependencies

- **Phase 1 Setup** → no dependencies.
- **Phase 2 Foundational** → depends on Setup. **Blocks every story.**
- **Phase 3 US1** → depends on Foundational. **MVP.**
- **Phase 4 R10 spike** → independent of US1, so it MAY run alongside it. **Blocks US2–US4.**
- **Phase 5 US2** → depends on US1 **shipped** and the spike answered.
- **Phase 6 US3** → depends on US2 (there must be a rendered tree to traverse).
- **Phase 7 US4** → depends on US3 (focus) and US1 (the rule).
- **Phase 8 Polish** → depends on all four stories.

### ⚠️ Story parallelism is FORBIDDEN here

The template's default — stories proceed in parallel once foundational work is done — **does not
apply**. US1 must ship with the pointer as the placement rule's sole caller; adding a keyboard
caller destroys that evidence permanently, and it cannot be recovered afterwards. The only
sanctioned parallelism across phases is the R10 spike alongside US1.

### Parallel opportunities within a phase

- T003/T004/T005, T008/T010/T011 — different files.
- T012–T019 — different files.
- All `Tests first` blocks within a single story.

---

## Implementation strategy

1. Phase 1 + Phase 2 → foundation.
2. Phase 3 → **stop, validate, ship US1 alone.**
3. Phase 4 spike, in parallel with 3 if capacity allows → answer R10 before building on it.
4. Phases 5 → 6 → 7, strictly in order, validating at each checkpoint.
5. Phase 8, then the adoption slice in the consumer's repository, **then** the tag.

## Notes

- Every `Watch … fail` task is a real gate, not bookkeeping. A guard never seen red is unverified.
- ⚠️ Three tasks exist specifically because the equivalent assertion was **missing** when the
  source application shipped: T066 (the announced name), T079 (`put_down`), T082 (`already_last`).
- Commit per task or logical group; Conventional Commits (`AGENTS.md` R-034).
- Do not push, tag or release unless asked.

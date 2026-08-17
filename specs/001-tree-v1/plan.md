# Implementation Plan: laravel-tree v1 — core placement rule and accessible Filament tree page

**Branch**: `001-tree-v1` | **Date**: 2026-08-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-tree-v1/spec.md`

## Summary

Extract the adjacency-list tree that runs in the consumer application into a reusable
package: a plain-Laravel core that resolves "place this node beside that one" into a stored
sibling order, and an optional Filament v5 bridge that renders it as a drag-and-keyboard tree
page.

The core is the product. The bridge is the reason people will install it.

**Technical approach**: four stories built in dependency order, each independently shippable.
The placement rule lands first with the pointer as its only caller, exactly as the source
application did — because once a second caller exists, no test result can say which one made
it pass. Styling and JavaScript ship compiled and committed, registered through Filament's
asset manager, so no consumer needs a bundler or a theme change.

## Technical Context

**Language/Version**: PHP 8.3+

**Primary Dependencies**: `illuminate/database` and `illuminate/support` ^13;
`staudenmeir/laravel-adjacency-list` ^1.26. Filament v5 in `require-dev` and `suggest` only.

⚠️ **Amended twice during implementation** (constitution 1.1.0, then 1.2.0). This said
`^11 | ^12 | ^13` with adjacency-list `^1.26`, which was **internally unsatisfiable**: that
release requires `illuminate/database ^13.0` only, so `^12` could never have resolved alongside
it. Laravel 11 was dropped because all 108 of its published releases carry unresolved security
advisories. Laravel 12 was then dropped although it WORKS, because it has no consumer — the
consumer application is on `^13.17` — and because widening a range later is non-breaking while
narrowing it after publication is not. With Laravel 13 alone, `^1.26` is once again the right pin.

**Storage**: The host's database, through the host's own Eloquent model. The package owns no
tables and ships its schema change as a publishable stub.

**Testing**: Pest 4 on Orchestra Testbench. Three suites: core (with Filament **uninstalled**),
bridge (panel harness), and browser (drag, keyboard, axe).

**Target Platform**: Any Laravel application. The bridge additionally requires a Filament v5
panel.

**Project Type**: Library (single package, conditional bridge — not a monorepo, and not split
into `laravel-x` + `filament-x`).

**Performance Goals**: No throughput target. The one measurable constraint is query shape: a
move must not scale its query count with the size of the tree, only with the size of the
affected group.

**Constraints**: No automatic migration. No host build step. No opinion about visibility. No
audit dependency. Rendering must be correct in light and dark.

**Scale/Scope**: Trees of the size an admin panel displays — hundreds of nodes, not millions.
Deep hierarchies are supported; virtualised rendering is not in v1.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Gates derived from `.specify/memory/constitution.md` v1.0.1.

| Gate | Principle | Status | How this plan satisfies it |
|---|---|---|---|
| Tests precede implementation, and each is watched failing | I | ✅ PASS | Every story's tasks begin with a failing test. `contracts/` fixes the surface first so a test can be written against it before code exists |
| No index in any public entry point | II | ✅ PASS | `contracts/public-api.md` defines placement solely as reference sibling + `SiblingPlacement`. There is no overload that takes an index |
| Complete group vs rendered siblings kept distinct | II | ✅ PASS | Two separate parameters, named differently, documented as the distinction the package exists to preserve |
| No migration, no forced translation, no visibility opinion | III | ✅ PASS | Migration is a `.stub`; copy is publishable; visibility arrives as an argument |
| No audit/auth dependency | III | ✅ PASS | `NodeMoved` / `SiblingsReordered` events; nothing writes a log |
| Roving tabindex, ARIA on the focusable node, rendered counts | IV | ✅ PASS | Story 3's acceptance scenarios are written as the assertions |
| Announcements not claimed without a real screen reader | IV | ⚠️ **PASS WITH A NAMED GAP** | SC-011 is separated from the other criteria and will be reported **unproved**. See Complexity Tracking |
| Core loads without Filament, proven in CI | V | ✅ PASS | Dedicated CI job removes `filament/filament` and runs the core suite |
| Genericity proven by a second model | V | ✅ PASS | A `Page` model in `tests/` alongside the tree fixture |
| No host bundler, theme edit, or content glob | VI | ⚠️ **PASS, WITH ONE CORRECTION** | Compiled CSS/JS committed and registered via `FilamentAsset`. ⚠️ The claim needed qualifying — see below |
| Runtime only in the distributed archive | VI | ✅ PASS | `.gitattributes` `export-ignore` before the first tag; blocked as a release gate, not polish |

⚠️ **One constitutional claim was checked and needed qualifying, which is exactly what
Principle VI's own rule demands.** Principle VI says a host must install the package "without
running a bundler, editing a theme, or adding a build step". Filament copies registered assets
into `/public` via `php artisan filament:assets`, so a command *does* run. That command is not
a new burden — Filament's installer already wires `filament:upgrade` into `post-autoload-dump`,
so it fires on every `composer install` in a normal Filament application — but the honest
statement is **"no bundler, no npm, no theme change, and no step a Filament app does not
already run"**, not "no command at all". Recorded in `research.md` R6 rather than left as an
overclaim.

**Re-check after Phase 1** — run 2026-08-14 against `contracts/public-api.md`, `data-model.md`
and `quickstart.md`. Every gate above still holds, and the design surfaced **three things the
pre-research check could not see**:

1. ⚠️ **`MoveNode` takes an integer position, which looks like the thing Principle II forbids.**
   It is not — that integer is one the package itself produced via `ResolveSiblingPlacement`,
   and `PlaceNode` is the composed entry point hosts are told to call. But the distinction lives
   in a doc comment, which is weak enforcement for the package's most important rule. **Carried
   into tasks as a required test**: a host calling `MoveNode` directly with a hand-computed index
   is the one way to reintroduce the 071 defect, and the contract should make that hard rather
   than merely discouraged.
2. ✅ **`$renderedSiblingIds` must have no default value**, which the contract now states. An
   empty default would silently disable the visibility refusal — turning a security check into a
   no-op for every caller who omitted it. This is a stronger form of Principle II than the
   pre-research check had articulated.
3. ✅ **The three `UnreachableReference` causes deliberately share one exception type.**
   Separating them would let a caller distinguish "exists but hidden from you" from "does not
   exist" — the same disclosure FR-037 prevents in the ARIA counts. Principle IV's reasoning
   turned out to constrain the *core*, not only the bridge.

**One gate remains conditionally passed**: SC-011, unchanged and tracked in Complexity Tracking.

⚠️ **And one item is genuinely unresolved rather than passed**: research **R10** (browser testing
from inside a package) is **[open]**. Three of the four stories depend on it. It MUST be settled
by a spike before Phase 2 begins — "prove fragile seams first" — and `/speckit-tasks` should
order it accordingly rather than assuming the harness works.

## Project Structure

### Documentation (this feature)

```text
specs/001-tree-v1/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0 output — decisions and their alternatives
├── data-model.md        # Phase 1 output — contract, config, events, schema stub
├── quickstart.md        # Phase 1 output — install, adopt, and the verification procedures
├── contracts/
│   └── public-api.md    # The whole public surface. Anything absent is internal
└── tasks.md             # Phase 2 — created by /speckit-tasks, NOT by this command
```

### Source Code (repository root)

```text
src/
├── TreeServiceProvider.php          # registers config, translations, stub; conditionally boots the bridge
├── Contracts/
│   └── TreeNode.php                 # what a host model must answer
├── Concerns/
│   └── IsTreeNode.php               # adjacency-list relationships + position mechanics
├── Enums/
│   └── SiblingPlacement.php         # Before | After | LastChild
├── Actions/
│   ├── ResolveSiblingPlacement.php  # the rule — pure, no Filament, no host model named
│   ├── MoveNode.php                 # re-parent + renumber, atomic, fires NodeMoved
│   └── ReorderSiblings.php          # same-parent case
├── Events/
│   ├── NodeMoved.php
│   └── SiblingsReordered.php
├── Exceptions/
│   ├── CycleException.php
│   ├── InvalidTargetException.php
│   └── UnreachableReferenceException.php
└── Filament/                        # the bridge — loaded only when Filament is present
    ├── FilamentTreeServiceProvider.php
    └── Pages/TreePage.php           # abstract; the host extends it

config/tree.php                      # parent column, position column, tiebreaker
database/migrations/add_tree_columns.php.stub
lang/en/tree.php                     # publishable defaults
resources/
├── views/{tree,tree-branch}.blade.php
├── css/tree.css                     # source
├── js/tree.js                       # source — the ex-inline Alpine controller
└── dist/{tree.css,tree.js}          # COMMITTED build output
tests/
├── Core/                            # runs with Filament uninstalled
├── Bridge/                          # panel harness
├── Browser/                         # drag, keyboard, axe
└── Fixtures/{Category.php,Page.php} # two unrelated models — one proves nothing
```

⚠️ **Two entries in the tree above did not survive implementation, recorded rather than
quietly deleted:**

- `Concerns/InteractsWithTree.php` was never created. Its work — the host hooks, the read
  helpers, the committing entry points — is all on `TreePage` itself, and splitting it across a
  trait would have added a seam with nothing on either side of it. Removed from the structure.
- `Testing/AssertsTree.php` was promised in `contracts/public-api.md` as **public surface**,
  was never built, and no task in T001–T100 covered it. Found by `/speckit-analyze` (T096) and
  **RETRACTED** rather than written: the contract itself described the helpers as not required,
  and building public surface with no consumer immediately before the adoption slice is the
  mistake R-035 names. Nothing was published under the name, so the migration is empty. See the
  amendment in `contracts/public-api.md`.

**Structure Decision**: Single package with a conditionally-registered bridge, per
`the consumer's package-documentation rule` rule 2 in the consumer's repository. Not split into two repositories:
that is warranted only when a Filament major breaks the bridge while the core is untouched, and
pre-splitting costs a subtree-split pipeline for no present benefit.

## Phased delivery

Each phase is a shippable increment matching one user story. **The order is not
interchangeable** — see the note under Phase 1.

| Phase | Story | Delivers | Proven by |
|---|---|---|---|
| **0** | — | Package skeleton, CI, the three suites wired and empty-but-passing | CI green on all legs |
| **1** | US1 (P1) | The placement rule, contract, events, exceptions, config, schema stub | Core suite with Filament uninstalled, two models |
| **2** | US2 (P2) | `TreePage`, both blades, package CSS, pointer drag, search, confirmation | Bridge suite + browser drag tests, styled in a themeless panel |
| **3** | US3 (P3) | Roving tabindex, arrow/Home/End traversal, ARIA, row naming | Keyboard browser tests + axe in light and dark |
| **4** | US4 (P4) | Pick up / move / put down / cancel / abandon, with announcements | Keyboard browser tests asserting announced strings and stored order |

⚠️ **Phase 1 must ship with the pointer as the rule's only caller — and Phase 3/4 must not be
merged into Phase 2.** This is inherited from the source application, where US1 shipped alone
and deliberately: the shared placement rule is provable with exactly one caller exactly once,
and once a keyboard caller exists, no test result can say which of the two made it pass. The
evidence is not reproducible later.

## Complexity Tracking

> Filled because the Constitution Check has one gate that cannot be fully met in v1.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| **SC-011 (screen-reader walk) will be unproved at v1** | Principle IV requires announcements be walked with a real screen reader by someone who can hear them. No machine with a screen reader and audio is currently available — the same constraint has left two such walks outstanding in the source application since 2026-08-13 | ⚠️ **The tempting alternative is the dangerous one.** Substituting an accessibility-tree dump or an axe pass would satisfy the gate on paper while proving something else entirely: axe proves a name *exists*, a dump proves the *data* a reader receives. The source application shipped four *correct* ARIA assertions while the announced name was wrong. So the gap is **named and reported unproved** rather than closed with weaker evidence — Principle I's "a named gap is a decision, an unnamed one is a violation" |
| **Browser suite requires a served panel inside a package** | US2–US4 are unprovable without a real browser: a test harness serves no compiled CSS, so no assertion in it can show a focus ring is visible or a drag preview lands | Unit-testing the Alpine controller in isolation was rejected — it would prove the functions run, not that the tree is operable, and every defect the source application found late was found by a live walk rather than by its 2,160 passing tests |

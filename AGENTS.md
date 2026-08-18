# AGENTS.md

Core rules for agents working in `rolland97/laravel-tree`.

Every rule below cites its source. Nothing here is invented preference. If a rule and its
source ever disagree, **the source wins and this file is the bug**.

**Scope**: this file governs planning and implementation in this repository. It is loaded as a
mandatory gate before `/speckit-plan` (`.specify/extensions.yml` → `hooks.before_plan`).

⚠️ **Read this before trusting a citation.** When this file was written the repository contained
**no `composer.json`, no `phpunit.xml`, no CI workflow and no `src/`**, so most rules cited a
**decision document** rather than the config file that would eventually enforce them.

**That is no longer true.** `composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`, `pint.json`,
`.github/workflows/tests.yml` and `src/` all exist, and the four rules that were tagged
`[pending artifact]` — R-029, R-031, R-032, R-033 — have been **re-cited against the real files
and retagged `[ratified]`**, each naming the specific setting that enforces it.

⚠️ Note for anyone reconciling documents: `tasks.md` **T093** says there are **five**
`[pending artifact]` rules. There were **four** — R-029, R-031, R-032, R-033. The count was wrong
when written; the rules themselves were not. (An earlier revision of this note blamed `CLAUDE.md`
for the miscount, which was itself wrong: `CLAUDE.md` never states a number.)

Each rule is tagged:

- **[ratified]** — the source exists in this repository now and can be read today.
- **[inherited]** — the source is in the consumer application, which this package is
  extracted from. Path given; that repository is the authority.
- **[pending artifact]** — the decision is settled but the file that will express it does not
  exist yet. ⚠️ **When the plan creates that file, come back and re-cite this rule against it.**
  A rule left citing an intention after its artifact exists is exactly the drift MemoryLint is
  installed to catch.

---

## The package boundary — what this must never become

- **R-001** — The core in `src/` MUST NOT require `filament/filament`, and MUST NOT fail to load
  when Filament is absent. The bridge lives under `src/Filament/` with Filament in `require-dev`
  and `suggest` only, registered conditionally. *Source: constitution Principle V; spec FR-020.*
  **[ratified]**
- **R-002** — Do **not** ship a migration that runs automatically. Schema changes ship as a stub
  the host publishes and runs. *Source: constitution Principle III; spec FR-018.* **[ratified]**
- **R-003** — Do **not** decide who may see a node. Visibility is the host's; the package receives
  it as an argument. There is no default, because a default here fails in the unsafe direction.
  *Source: constitution Principle III; spec FR-017, Assumptions.* **[ratified]**
- **R-004** — Do **not** depend on an activity log, an audit trail, or an authentication
  mechanism. Fire a domain event; the host listens. *Source: constitution Principle III; spec
  FR-014, FR-015.* **[ratified]**
- **R-005** — Do **not** add merge, bulk-assign, edit, retire/restore, or record-listing
  behaviour. These are the host's product and are contributed through row and header slots.
  *Source: spec § Out of Scope.* **[ratified]**
- **R-006** — All copy the package emits MUST be host-overridable. Column names and the read
  tie-breaker are configuration, not constants. *Source: constitution Principle III; spec
  FR-011, FR-012, FR-019.* **[ratified]**

## The placement rule — the reason this package exists

- **R-007** — A placement is a **reference sibling plus a placement** (`Before`, `After`,
  `LastChild`). **No public entry point may accept a caller-supplied numeric index** — not as an
  overload, not deprecated, not behind a flag. *Source: constitution Principle II; spec FR-001,
  FR-002.* **[ratified]**
- **R-008** — Resolve against the **complete** destination group, while accepting the siblings the
  caller could see as a **separate** argument. Refuse a reference that is not in the complete
  group, was not among the visible ones, or is the node being moved. *Source: constitution
  Principle II; spec FR-003…FR-006.* **[ratified]**

  > ⚠️ **Why this is constitutional rather than stylistic.** The browser cannot count rows it never
  > drew. Privacy scoping and quick-search both remove rows from the DOM, so an index counted
  > client-side resolves server-side against a different list. In the source application this
  > silently renumbered a partial list and left the rest holding stale, colliding positions — one
  > actor's reorder shuffled nodes for a *different* actor. Accepting a client index is also an
  > authorization hole: it lets a tampered payload address a node privacy hides.

- **R-009** — After any move, every node in the affected group MUST hold a **contiguous,
  collision-free** sequence of positions, and the move plus its renumbering MUST be atomic.
  *Source: spec FR-010, FR-016.* **[ratified]**
- **R-010** — Refusals MUST be distinguishable by type (cycle, invalid target, unknown reference,
  unseen reference, self-reference), not a single generic exception. *Source: spec FR-013,
  SC-004.* **[ratified]**
- **R-011** — A completed interaction fires **exactly one** move event, however many keystrokes or
  pointer events produced it. *Source: spec FR-041, SC-009.* **[ratified]**

## Accessibility

- **R-012** — The whole tree is **one tab stop** (roving tabindex). Arrows traverse displayed rows
  **without wrapping**; Right/Left expand-then-descend and collapse-then-ascend; Home/End jump to
  the ends. *Source: spec FR-031…FR-034.* **[ratified]**
- **R-013** — `role`, `tabindex` and every `aria-*` property MUST sit on the **same** element —
  the one that actually takes focus. *Source: constitution Principle IV; spec FR-035.*
  **[ratified]**
- **R-014** — A row MUST be announced by **its own name only** — not its badges, not its action
  buttons, and not, expanded, its subtree. **Assert the announced name directly**; asserting the
  other ARIA properties is not a substitute. *Source: constitution Principle IV; spec FR-036.*
  **[ratified]**

  > ⚠️ `role="treeitem"` computes its name **from its contents**. The source application shipped an
  > unlabelled row that announced its short code, its vendor count, all six action labels and its
  > whole subtree — while **four correct `aria-*` assertions passed**. Automated checks cannot see
  > this, because a name does exist. *Inherited: the consumer's package notes
  > § Traps already paid for; the consumer's keyboard-order slice validation log.*

- **R-015** — Position and set size MUST count the **rendered** siblings, never the true group
  size. This is a **privacy** requirement before it is a convention: the true size discloses that
  a node exists which the actor may not see. *Source: constitution Principle IV; spec FR-037.*
  **[ratified]**
- **R-016** — A control duplicating a state already announced on the row MUST be hidden from
  assistive technology, **not** given a label. *Source: spec FR-038.* **[ratified]**
- **R-017** — Every transition in a keyboard move — pick up, move, put down, cancel, abandon,
  refuse, only-child, already-first, already-last — MUST be announced, and the announcement MUST
  survive a re-render the package did not initiate. *Source: spec FR-039, FR-040, FR-043,
  FR-044.* **[ratified]**

  > ⚠️ Two ways this broke in the source application. A live region's content is **client state the
  > server knows nothing about**, so a re-render wiped it — the region needs to be excluded from
  > morphing. And a host panel polling a notification bell every 30 seconds silently abandoned
  > every held node until the morph hook checked *which* component had morphed. *Inherited:
  > the consumer's own working log, § What US3 cost.*

- **R-018** — ⚠️ **Never record an accessibility-tree dump or an automated pass as evidence that
  announcements work.** Axe proves a name *exists*; a dump proves the *data* a reader receives.
  Neither proves the announcements make sense as heard sentences. A criterion not walked with a
  real screen reader MUST be reported **unproved**. *Source: constitution Principle IV; spec
  SC-011.* **[ratified]**

## Styling and assets

- **R-019** — A host MUST get a correctly styled tree with **no bundler, no theme edit and no
  build step of its own**. Package styling ships **compiled and committed**, registered as a
  Filament asset. *Source: constitution Principle VI; spec FR-028; the
  consumer's package-documentation rule 3.* **[ratified]**
- **R-020** — Package markup MUST NOT contain bare host-framework utility classes, and the package
  MUST NOT ask a host to point a content glob into `vendor/`. Use Filament's own components for
  anything they cover, and package-owned classes for the rest. *Source: constitution Principle VI;
  spec FR-029.* **[ratified]**

  > ⚠️ **This is why the source blades are a REWRITE, not a port.** A Filament panel compiles only
  > the utilities its own CSS references, so a blade living in `vendor/` renders unstyled — and the
  > failure is silent, reading as a bug in the host's own CSS. PKG-01 originally called the branch
  > blade "mostly generic", which was wrong and made the work look smaller. *Inherited: the
  > consumer's package notes, § Source inventory.*

- **R-021** — Inherit the host panel's accent colour through its theme variables rather than
  defining a palette. Render correctly in **both** light and dark. *Source: spec FR-030.*
  **[ratified]**
- **R-022** — Drag MUST be initiated only from a dedicated handle, so activating a row action never
  starts a drag. *Source: spec FR-023.* **[ratified]**

## Testing

- **R-023** — Write the test first, and **watch it fail**. A test whose red has never been seen is
  **unverified**. Where a guard cannot be made to fail, investigate rather than accept it — four
  such probes in the source application each exposed a real defect. *Source: constitution
  Principle I.* **[ratified]**
- **R-024** — Assertions about ordering MUST read the **stored rows**, never the rendered output.
  A test comparing a page against the same accessor the page rendered from cannot see a defect
  inside that accessor. *Source: constitution Principle I; spec FR-047.* **[ratified]**
- **R-025** — Fixtures proving an ordering rule MUST be named so a correct and an incorrect
  implementation produce **different** output. *Source: constitution Principle I; spec FR-048.*
  **[ratified]**

  > ⚠️ Two of fourteen guards in the source application passed against the defect as first written,
  > because the bug wrote colliding positions and the read tie-broke by **name** — so both
  > implementations produced the same visible order. Fixtures were renamed so they could not.
  > *Inherited: the consumer's package notes, § Traps, item 2.*

- **R-026** — The suite MUST include a **second, unrelated model**. One consumer proves nothing.
  *Source: constitution Principle V; spec FR-045, SC-010.* **[ratified]**
- **R-027** — CI MUST run the core suite with **`filament/filament` uninstalled**. Asserting the
  boundary is not proving it. *Source: constitution Principle V; spec FR-046.* **[ratified]**
- **R-028** — Anything visual or focus-related MUST be verified in a **real browser, in both light
  and dark**. A test harness serves no compiled CSS, so no assertion in it can prove a focus ring
  is visible or a colour resolves. *Source: constitution § Development Workflow.* **[ratified]**
- **R-029** — Tests MUST NOT depend on execution order and MUST NOT print. *Source: constitution
  Principle I.* **[ratified — `phpunit.xml.dist`: `executionOrder="random"`,
  `beStrictAboutOutputDuringTests="true"`, `failOnWarning`, `failOnRisky`,
  `failOnEmptyTestSuite`.]**
- **R-030** — ⚠️ Renaming a method is a real safety mechanism; **named arguments are not**. A
  Livewire component test dispatches through the container's method injection, which matches by
  name and **silently discards unknown keys** — a probe passed two arguments the method did not
  declare and ran clean on defaults. When a signature's meaning changes, **rename it**. *Inherited:
  the consumer's package notes, § Traps, item 1.* **[inherited]**

## Static analysis, style, Git and release

- **R-031** — PHPStan over `src` and `config` at a level that MUST NOT be lowered. Do not add new
  code to a baseline to silence an error. *Source: constitution § Platform and Toolchain.*
  **[ratified — `phpstan.neon.dist`: `level: 8` over `src` and `config`, no baseline.]**

  > Static analysis is load-bearing here, not cosmetic: in the source application it caught ids
  > plucked as `array<mixed>` being compared **strictly**, so a key arriving from the driver as a
  > numeric string would never have matched its own group.

- **R-032** — Pint owns formatting. Do not hand-format against it. *Source: constitution §
  Platform and Toolchain.* **[ratified — `pint.json`, `laravel` preset.]**
- **R-033** — CI actions MUST be pinned to a full commit SHA with the human-readable version in a
  trailing comment, and every workflow MUST declare an explicit least-privilege `permissions:`
  block. *Source: constitution § Platform and Toolchain.* **[ratified —
  `.github/workflows/tests.yml`: 12 actions pinned to full commit SHAs with trailing version
  comments, 0 unpinned, and an explicit `permissions:` block on every job.]**
- **R-034** — Conventional Commits, lowercase descriptive subject, saying what changed and **why it
  mattered**. Do not commit, push, tag, or release unless asked. *Source: constitution §
  Development Workflow.* **[ratified]**
- **R-035** — ⚠️ **Do not tag until a real consumer has been built against this package
  unpublished.** A published version cannot be retracted, and an API frozen without a consumer is
  frozen against guesses. The first consumer is a private application, adopted through a
  local path reference. *Source: constitution § Development Workflow; spec § Assumptions;
  the consumer's package notes, D4.* **[ratified]**
- **R-036** — The repository is **private** until that adoption is green. Distribution archives
  MUST exclude specs, agent configuration, tests and build sources **before** the first tag.
  *Source: constitution Principle VI; the consumer's package notes, D5.*
  **[ratified]**

⚠️ **R-035 and R-036 were both SATISFIED on 2026-08-18, and the rules stand as written.** The
consumer was built against this package unpublished, its suite went green in CI, and only then was
`v0.9.0` tagged and the repository made public — the order R-035 requires. R-036 was verified
rather than assumed: `git archive v0.9.0 | tar -t` ships only `LICENSE`, `README.md`,
`composer.json`, `config/`, `database/`, `lang/`, `resources/` and `src/`.

⚠️ **One thing R-036 does not say, and should be read alongside it.** Going public needed the git
**history** and the commit **messages** scrubbed too, not just the working tree — and force-pushed
objects survive on GitHub, measured by fetching one back after a force-push. The repository was
deleted and recreated, then checked with an anonymous clone. Archive hygiene and disclosure hygiene
are different problems.

## Working agreements

- **R-037** — **A quoted constraint is a claim.** Where any document asserts something is
  forbidden, fixed, or already handled, verify it against the artifact it describes before using
  it as a reason not to do work. A constraint that **forecloses** work deserves more verification
  than one that permits it. *Source: constitution § Development Workflow.* **[ratified]**

  > ⚠️ Twice now this has been the expensive one. In the source application a contract asserted a
  > namespace was read-only "and arch rule 9b keeps it that way"; rule 9b constrains something
  > else entirely, and the misreading was repeated into commits, a merge-request note and the
  > resume docs — every time as a reason something could not be done — until the test was finally
  > opened. In *this* package, PKG-01's "mostly generic" verdict on the branch blade was wrong in
  > the direction that made the work look smaller (see R-020).

- **R-038** — Report outcomes faithfully. If tests fail, say so with the output. If a step was
  skipped, say that. Work that was not run MUST NOT be described as verified. *Source:
  constitution § Development Workflow.* **[ratified]**
- **R-039** — Prove fragile seams **before** building on them. Where behaviour depends on another
  framework's internals, prove that dependency end to end in a harness first — discovering a wrong
  assumption after the dependent code exists converts a spike into a rewrite. *Source: constitution
  § Development Workflow.* **[ratified]**
- **R-040** — This package is an **extraction**. Where this file, the spec, or PKG-01 disagrees
  with the behaviour running in the source application, the **running behaviour is presumed right**
  and the disagreement is a finding to investigate. It has been wrong before. *Source: spec §
  Assumptions.* **[ratified]**

---

## Mapping to the constitution

`.specify/memory/constitution.md` says **why**; this file says **what, here, with sources**.
They MUST NOT contradict each other; where they appear to, the constitution governs and this
file is corrected.

| Principle | Rules |
|---|---|
| I. Test-First, and a guard that has never failed is not a guard | R-023…R-025, R-029 |
| II. A move names a neighbour, never an index | R-007…R-011 |
| III. The host owns privacy, persistence and words | R-002…R-006 |
| IV. Accessible by construction, never claimed without evidence | R-012…R-018 |
| V. Core is Laravel; Filament is an optional bridge | R-001, R-026, R-027 |
| VI. Ship runtime only, built and committed | R-019…R-022, R-036 |
| Platform and Toolchain | R-031…R-033 |
| Development Workflow | R-028, R-034, R-035, R-037…R-040 |

⚠️ **The constitution's Governance table currently records "no `AGENTS.md`".** That is now stale
— update it to point at this file in the same change that merges this one, or the two documents
disagree about whether this one exists.

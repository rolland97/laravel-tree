<!--
SYNC IMPACT REPORT — 2026-08-14
================================================================================
Version change: (unversioned template) → 1.0.0
Bump rationale: First ratification. Every principle is new; there is no prior
version to be incompatible with, so MAJOR is not warranted and 1.0.0 is the
initial adoption.

Modified principles: none (initial adoption)

Added sections:
  - I.   Test-First, and a Guard That Has Never Failed Is Not a Guard
         [replaces PRINCIPLE_1 placeholder]
  - II.  A Move Names a Neighbour, Never an Index
  - III. The Host Owns Privacy, Persistence and Words
  - IV.  Accessible by Construction, and Never Claimed Without Evidence
  - V.   Core Is Laravel; Filament Is an Optional Bridge
  - VI.  Ship Runtime Only, Built and Committed
  - Platform and Toolchain Constraints    [replaces SECTION_2 placeholder]
  - Development Workflow and Quality Gates[replaces SECTION_3 placeholder]
  - Governance

Removed sections: none

Templates and artifacts requiring updates:
  ✅ .specify/templates/plan-template.md   — generic "Constitution Check" gate
       section exists; no edit needed. Plans derive real gates from this file.
  ✅ .specify/templates/spec-template.md   — no constitution-driven mandatory
       sections added or removed; no edit needed.
  ✅ .specify/templates/tasks-template.md  — its test-task guidance says tests
       are OPTIONAL. Principle I makes them mandatory here. Resolved per-feature
       in specs/001-tree-v1/tasks.md, which overrides the template default in its
       own opening section, rather than editing the shared template — matching how
       filament-tours handled the same conflict.
  ✅ README.md                             — WRITTEN during 001-tree-v1. It does
       not tell hosts to publish a stylesheet or add an @source glob. It states
       the QUALIFIED form of Principle VI's claim rather than the overclaim:
       "no bundler, no npm, no theme change, and no step a Filament app does not
       already run" — because `php artisan filament:assets` IS a command, even
       though Filament's installer already wires it into post-autoload-dump.
       See research.md R6, which corrected this principle's own wording.
  ✅ AGENTS.md                             — WRITTEN 2026-08-14, hours after
       ratification, which is why this document is already at 1.0.1. 40 rules,
       each tagged [ratified] / [inherited] / [pending artifact] according to
       whether its cited source exists in this repository today. The Governance
       mapping table below is now real; it was deliberately empty for the length
       of one commit rather than omitted.

Deferred TODOs: none. RATIFICATION_DATE is the repository's first-commit date
(2026-08-14), which is when the project was adopted.

⚠ Provenance: Principles II and IV are not authored here. They are the recorded
outcomes of slice 071 in the consumer application, which paid for them with
two live ordering defects and an accessible-name defect that four correct aria-*
assertions failed to catch. They are constitutional precisely so a later
convenience API cannot quietly re-introduce what they forbid. Evidence:
the consumer's keyboard-order slice/checklists/validation-log.md in that repo, and
the consumer's package notes § Traps already paid for.
================================================================================
-->

# laravel-tree Constitution

Adjacency-list tree management for Eloquent, plus an accessible drag-and-keyboard
Filament tree page.

## Core Principles

### I. Test-First, and a Guard That Has Never Failed Is Not a Guard

Every behaviour change MUST land with the test that would have caught its absence, and that
test MUST be written and observed failing before the implementation exists.

Observing the failure is not a formality and MUST NOT be skipped on the grounds that the
assertion is obviously correct. A test whose red has never been seen MUST be treated as
unverified, and where a guard cannot be made to fail, that fact MUST be investigated rather
than accepted — four probes in the source application failed to redden and every one exposed
a real defect behind the guard.

The suite runs in random order and fails on warnings, risky tests, empty suites, and stray
output. Tests therefore MUST NOT depend on execution order and MUST NOT print.

Two failure modes are named because this project has already paid for both:

1. **A test that compares an output against the same accessor that produced it cannot see a
   defect inside that accessor.** Assertions about ordering MUST read the stored rows, not the
   rendered list.
2. **A test may pass against a corrupt table.** Where a defect corrupts an ordering, fixtures
   MUST be named so that the broken and correct outputs differ — a tie-break by name makes two
   different implementations produce the same visible order.

**Rationale**: A package consumed inside other people's admin panels cannot be debugged by its
author when it breaks. In the application this package is extracted from, two of fourteen new
guards passed against the defect as first written, and the only thing that caught it was
asking why two were green.

### II. A Move Names a Neighbour, Never an Index

A request to place a node MUST identify a **reference sibling** and a **placement**
(`Before`, `After`, `LastChild`). It MUST NOT identify a position by numeric index supplied
from the client, and no public API MAY accept one — not as a convenience overload, not as a
deprecated path, not behind a flag.

The resolver MUST compute the index against the **complete** destination group, while the
caller supplies the set of siblings the actor could actually see. A reference that is not a
member of the complete group, or that the actor was never shown, MUST be refused rather than
resolved to a best guess.

**Rationale**: The browser cannot count rows it never drew. Privacy scoping and quick-search
both remove rows from the DOM, so an index counted client-side is resolved server-side against
a different list. In the source application this silently renumbered a partial list and left
the remainder holding stale, colliding positions — one actor's reorder shuffled categories for
a different actor, and no test noticed because the read tie-broke by name. Accepting a client
index is also an authorization hole: it lets a tampered payload address a node privacy hides.

### III. The Host Owns Privacy, Persistence and Words

This package MUST NOT decide who may see a node, MUST NOT run a migration of its own, and MUST
NOT assume a language.

Visibility is the host's: the package owns the ordering rule and receives the visible set as an
argument. Schema changes ship as a **stub** the host publishes and runs, never as an
auto-discovered migration. Copy ships as publishable defaults the host may override, and the
column names and tie-breaker are configuration, not constants.

The package MUST NOT depend on an activity-log, an audit trail, or an authentication mechanism.
Where the host needs to record that something happened, the package fires a domain event and the
host listens.

**Rationale**: Each of these is a decision the host has already made, usually differently than
we would. The source application logs moves through `spatie/laravel-activitylog` and scopes
visibility through its own `visibleTo()` scope; hard-coding either would force that choice onto
every consumer and make the package unusable to anyone who chose otherwise.

### IV. Accessible by Construction, and Never Claimed Without Evidence

The tree MUST be operable by keyboard alone, with a **roving tabindex** so the whole tree is one
tab stop. `role`, `tabindex` and every `aria-*` property MUST sit on the **same** node — the one
that actually takes focus.

`aria-posinset` and `aria-setsize` MUST count the **rendered** siblings, never the true group
size. This is a privacy requirement before it is a convention: the true size discloses that a
node exists which the actor is not permitted to see.

Every interactive element MUST have an accessible **name**, and that name MUST be asserted
directly. Asserting the other ARIA properties is not a substitute.

Claims of accessibility MUST match the evidence that exists:

- An automated check (axe) proves a name **exists**, never that it is sensible.
- An accessibility-tree dump proves the **data** a screen reader receives, never that the
  announcements work as heard sentences. It MUST NOT be recorded as if it did.
- A criterion that has not been walked with a real screen reader MUST be reported as unproved.

**Rationale**: `role="treeitem"` computes its name from its contents, so an unlabelled row
announces its short code, its badges, every action button and — expanded — its entire subtree.
The source application shipped exactly that while four *correct* `aria-*` assertions passed:
every property was asserted except the one a screen reader leads with. No automated check saw a
fault, because a name did exist.

### V. Core Is Laravel; Filament Is an Optional Bridge

`src/` MUST be plain Laravel and MUST NOT require `filament/filament`. It MUST remain usable
from an API, a console command, Inertia, or a Blade application that has never heard of Filament.

The Filament bridge lives under `src/Filament/`, with `filament/filament` in `require-dev` and
`suggest` only, and its provider registered conditionally. CI MUST prove this rather than assert
it: one job runs the core suite with `filament/filament` **uninstalled**.

Genericity MUST be demonstrated by a second model in the test suite that is not a category. One
consumer proves nothing.

**Rationale**: The reusable value is the rules — cycle guards, placement resolution, sibling
ordering — not the chrome around them. Coupling them makes the package unusable to any project
that did not also pick Filament, and ties its release cadence to Filament's majors.

### VI. Ship Runtime Only, Built and Committed

A host MUST be able to install this package and see a correctly styled, working tree without
running a bundler, editing a theme, or adding a build step.

Styling MUST therefore be package-owned: blades use Filament's own components plus classes this
package defines, shipped as a **compiled stylesheet registered as a Filament asset**. Blades MUST
NOT contain bare host-framework utility classes, and the package MUST NOT ask a host to point a
`@source` glob into `vendor/`. JavaScript ships the same way — a registered asset built from
source in this repository, with the compiled artifact committed.

Development tooling — specifications, agent configuration, tests, build sources — MUST be excluded
from the distribution archive **before** the first release tag.

**Rationale**: A Filament panel only compiles the utilities its own CSS references, so a blade
living in `vendor/` renders unstyled and the failure is silent — it looks like a bug in the
host's own CSS. The source application lost time to this twice. A tag cannot be retracted once
published, so archive hygiene is a release blocker rather than polish.

## Platform and Toolchain Constraints

**Platform**: PHP 8.3+. `illuminate/database` and `illuminate/support` ^13, and
`staudenmeir/laravel-adjacency-list` ^1.26. The Filament bridge targets v5. Support the full CI
matrix rather than the local version; a change that passes only on the newest combination is a
broken change.

⚠️ **Laravel 12 was dropped in 1.2.0, and Laravel 11 in 1.1.0. Neither is a loosening.**

Laravel 12 **works** — verified at 12.61.1 with Filament 5.6.5 — and was dropped anyway,
because it has **no consumer**. The one consumer this package exists for is on `^13.17`, and
`^12.0` would advertise ~60 patch releases that Composer's audit refuses. The asymmetry decides
it: widening support later is a MINOR, non-breaking change, while narrowing it after publication
is breaking. Support starts narrow and grows when a host actually needs it — the same reasoning
that keeps the package untagged until a real consumer has adopted it.

⚠️ **Laravel 11:** Every one of the 108
published `laravel/framework` 11.x releases carries unresolved security advisories —
`PKSA-mdq4-51ck-6kdq` alone spans `>=11.0.0,<12.0.0` with no patched release in the line — so
Composer's default `block-insecure` audit refuses to install any of them. The version claim could
not be honoured by any host, and the CI legs asserting it could never have gone green.
`^1.24` rather than `^1.26` because `staudenmeir/laravel-adjacency-list` pins one Laravel per
minor: `^1.26` requires `illuminate/database ^13.0` **only**, which silently made `^12` and `^13`
support mutually exclusive.

**Static analysis**: PHPStan over `src` and `config` at a level that MUST NOT be lowered. New code
MUST NOT be added to the baseline to silence an error. Static analysis is load-bearing here rather
than cosmetic: in the source application it caught a real defect where ids plucked as `array<mixed>`
were compared strictly, so a key arriving from the driver as a numeric string would never have
matched its own group.

**Formatting**: Pint owns formatting. Code MUST NOT be hand-formatted against it.

**Supply chain**: CI actions MUST be pinned to a full commit SHA with the human-readable version in
a trailing comment, and every workflow MUST declare an explicit least-privilege `permissions:` block.

**Comments**: Explain **why**, not what, and only where something is surprising.

## Development Workflow and Quality Gates

**Before implementation**: A feature's specification MUST be complete and its ambiguities resolved
before planning; planning MUST be complete before tasks; tasks MUST be ordered before implementation.
Each stage adopts its predecessor rather than re-deriving it.

**A quoted constraint is a claim.** Where a specification, plan, or inherited document asserts that
something is forbidden, fixed, or already handled, that assertion MUST be verified against the
artifact it describes before it is used as a reason not to do work. A constraint that **forecloses**
work deserves more verification than one that permits it.

**Prove fragile seams first**: Where a feature depends on another framework's internals, that
dependency MUST be proven end to end in a harness **before** code is built on top of it.

**Styling and accessibility are proven live**: A test harness serves no compiled CSS, so no
assertion in it can prove a focus ring is visible or a colour resolves. Anything visual MUST be
verified in a real browser, in **both** light and dark, before it is called done.

**Before completion**: The core suite (Filament uninstalled), the bridge suite, static analysis and
formatting MUST all pass, and any built front-end artifact MUST be rebuilt and committed. A stale
committed bundle ships broken code that every local check reports as green.

**Release**: The package MUST NOT be tagged until a real consumer has been built against it
unpublished. A published version cannot be retracted, and an API frozen without a consumer is
frozen against guesses.

**Commits**: Conventional Commits with a lowercase, descriptive subject. Say what changed and why it
mattered. Do not commit, push, tag, or release unless asked.

**Reporting**: Report outcomes faithfully. If tests fail, say so with the output. If a step was
skipped, say that. Work that was not run MUST NOT be described as verified.

## Governance

**Authority**: This constitution supersedes other practices in this repository. Where a practice and
a principle conflict, the principle wins and the practice is the bug.

**Relationship to `AGENTS.md`**: `AGENTS.md` is the operational expression of this document — 40
rules that agents load as a mandatory gate before planning. This file says *why*; `AGENTS.md` says
*what, here, with sources*. They MUST NOT contradict each other. Where they appear to, this
document governs and `AGENTS.md` is corrected.

⚠️ **Its rules cite decision documents, not config files, and say so per rule.** This repository
has no `composer.json`, `phpunit.xml`, CI workflow or `src/` yet, so a rule about PHPStan levels or
suite ordering cannot cite the file that will enforce it. Each such rule is tagged **[pending
artifact]** and MUST be re-cited when the plan creates that file. A rule still citing an intention
after its artifact exists is drift, and is what MemoryLint is installed to catch.

| Principle | AGENTS.md rules |
|---|---|
| I. Test-First | R-023…R-025, R-029 |
| II. A move names a neighbour | R-007…R-011 |
| III. Host owns privacy, persistence, words | R-002…R-006 |
| IV. Accessible by construction | R-012…R-018 |
| V. Core is Laravel, Filament is a bridge | R-001, R-026, R-027 |
| VI. Ship runtime only | R-019…R-022, R-036 |
| Platform and Toolchain | R-031…R-033 |
| Development Workflow | R-028, R-034, R-035, R-037…R-040 |

**Amendment procedure**: Amendments MUST be proposed as a documented change to this file, stating the
principle affected, the reason, and the migration for anything already built against the previous
wording. An amendment that loosens a principle MUST say what it is trading away. Amendments take
effect when merged, not when proposed.

**Versioning policy**: Semantic versioning of the constitution itself.

- **MAJOR** — a principle is removed, or redefined in a way that permits what it previously forbade.
- **MINOR** — a principle or section is added, or existing guidance is materially expanded.
- **PATCH** — clarification, wording, or typo fixes that do not change what is permitted.

**Compliance review**: Every implementation plan MUST include a Constitution Check evaluated against
this file, both before research and after design. A plan that cannot pass a gate MUST justify the
violation explicitly in its Complexity Tracking section rather than omitting the gate. Substituting a
different rule source for this file is permitted only when this file is absent or unfilled, and MUST
be labelled as a substitution rather than reported as a pass.

**Version**: 1.2.0 | **Ratified**: 2026-08-14 | **Last Amended**: 2026-08-17

<!--
AMENDMENT 1.1.0 → 1.2.0 (2026-08-17)
MINOR. § Platform and Toolchain narrows again: illuminate ^13 only, and
adjacency-list back to ^1.26 (which requires illuminate ^13 and is therefore the
correct pin once Laravel 12 is gone — the ORIGINAL spec value, correct for a
reason the spec had not established).

PRINCIPLE AFFECTED: none. Platform section, not a principle.

REASON: Laravel 12 is not broken — it was verified working at 12.61.1 with
Filament 5.6.5, full suite green. It was dropped because it has NO CONSUMER. The
consumer application, the first and only consumer and the release gate, is on
laravel/framework ^13.17. Supporting a version nobody uses costs six CI legs and
constrains every future change to an older API surface. `^12.0` was also not
honest: everything below 12.61.1 is advisory-blocked.

WHAT IS TRADED AWAY: installability on Laravel 12 for hosts who do not exist yet.
Stated explicitly per the amendment procedure. The trade is defensible only
because of an asymmetry — widening a version range later is MINOR and
non-breaking, narrowing it after publication is breaking — and nothing is
published.

MIGRATION: none. No consumer, nothing tagged.

⚠️ DISCOVERED BY BEING ASKED. Nobody derived this; the question "why are we using
Laravel 12?" was put directly, and the honest answer was that the range had been
INHERITED from the spec and narrowed only where it was provably uninstallable —
which is not the same as justifying what remained. Recorded because the failure
mode is general: a constraint can survive every review by never being the thing
under review.
-->

<!--
AMENDMENT 1.0.1 → 1.1.0 (2026-08-17)
MINOR. § Platform and Toolchain Constraints materially changed: Laravel 11 is no
longer a supported target, and the adjacency-list floor moved from ^1.26 to ^1.24.

PRINCIPLE AFFECTED: none. This is the Platform section, not a principle. Nothing
that was forbidden is now permitted, and nothing that was required is now optional
— which is why this is MINOR rather than MAJOR.

REASON: the previous wording stated a fact that is no longer true. All 108
published laravel/framework 11.x releases are affected by unresolved security
advisories (PKSA-mdq4-51ck-6kdq spans >=11.0.0,<12.0.0 with no patched release),
so Composer's default block-insecure audit refuses to install them. The package
could not have been installed on Laravel 11 by any host, and the CI legs asserting
that support could never have passed. Separately, staudenmeir/laravel-adjacency-list
pins one Laravel per minor — ^1.26 requires illuminate/database ^13.0 ONLY — so
the old pairing of ^1.26 with ^11|^12|^13 was internally unsatisfiable.

WHAT IS TRADED AWAY: support for an end-of-life framework version that cannot be
installed securely. Stated explicitly per the amendment procedure, though it is a
capability the project never actually had.

MIGRATION: none for anything built. composer.json, the CI matrix and plan.md were
already corrected during 001-tree-v1 when the contradiction was found by trying to
install each combination; this amendment brings the constitution into line with
what was verified rather than the reverse. No consumer exists yet — nothing is
tagged or published.

DISCOVERED BY: attempting each matrix combination locally rather than trusting the
stated one, which is this document's own "a quoted constraint is a claim" rule
applied to itself.
-->

<!--
AMENDMENT 1.0.0 → 1.0.1 (2026-08-14)
PATCH. `AGENTS.md` was written, so the Governance section's statement that it did
not exist — and its deliberately empty mapping table — became false within hours
of ratification. Replaced with the real mapping, plus a note that its rules cite
decision documents rather than config files and carry a [pending artifact] tag
where the enforcing file does not exist yet.

PATCH rather than MINOR: nothing a principle permits or forbids changed. This is
the constitution catching up with a fact about the repository, which is the
failure mode its own "a quoted constraint is a claim" rule exists to prevent —
recorded here rather than fixed silently.
-->


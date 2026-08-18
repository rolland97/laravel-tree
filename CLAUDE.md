# laravel-tree

Adjacency-list tree management for Eloquent, plus an accessible drag-and-keyboard Filament v5
tree page. Extracted from a private consumer application, where every behaviour here already runs
in production.

⚠️ **This file is a pointer, not a summary.** It names where each authority lives and stops.
Restating their contents here would create a second copy that goes stale — the failure this
project's parent repository documents at length after an audit found nine phantom rows in a
backlog index.

---

## ⚠️ Read this before the first action

**`main` is the branch now, and it carries everything.** ⚠️ This paragraph used to say the
opposite — that `main` held only the scaffold and that a session landing there would not see the
rules it was gated by. That was true until **2026-08-18**, when `001-tree-v1` fast-forwarded into
`main` for the `v0.9.0` release. The two refs are the same commit; either is safe to work from.

```bash
git switch main        # or 001-tree-v1 — identical, and `main` is where releases are cut
```

Read in this order:

| # | File | What it is |
|---|---|---|
| 1 | `.specify/memory/constitution.md` | Six principles and **why**. Supersedes every other practice here |
| 2 | `AGENTS.md` | The same principles as 40 operational rules, each citing its source. **Mandatory gate before `/speckit-plan`** |
| 3 | `specs/001-tree-v1/spec.md` | 4 user stories, 48 requirements, 11 success criteria |
| 4 | `specs/001-tree-v1/plan.md` | Phases, the constitution check, and what the post-design re-check found |
| 5 | `specs/001-tree-v1/tasks.md` | **100 tasks, T001–T100.** This is the work |
| — | `research.md`, `data-model.md`, `contracts/public-api.md`, `quickstart.md` | Decisions, shapes, the public surface, and the verification procedures |

## Where the work is

**Phases 1–8 are built.** `src/`, `composer.json` and three suites all exist, and the four user
stories are implemented. Run the checks before trusting any of that:

```bash
composer test        # 332 passing across core, bridge and browser
composer analyse     # PHPStan level 8 over src and config
composer test:lint   # Pint
```

⚠️ **The browser suite needs Playwright.** `npm install && npx playwright install chromium`,
or every browser test errors rather than fails.

⚠️ **Rebuild the assets after touching `resources/css/` or `resources/js/`.** `npm run build`
writes the committed `resources/dist/`. A stale committed bundle ships broken code that every
local check reports as green.

**What is left**: only `T099` — see below. `T096` and `T100` are done. Everything else is
marked `[X]` in `tasks.md`, and every `Watch … fail` gate is recorded in
`specs/001-tree-v1/checklists/validation-log.md`, including **ten findings** where a guard could
not be made to fail and had to be investigated.

⚠️ **Read the validation log before adding a guard.** Three of those findings are the same shape:
correct code protected by two independent mechanisms, where no single mutation can show either is
needed. Do not "tidy away" one on the evidence that the suite stays green without it.

## The four things that will cost real time if ignored

Each is stated in full where it belongs; these are the pointers.

1. ✅ **Research `R10` is now `[verified]`** — settled by the `T043`–`T047` spike before any of
   US2–US4 was built on it. A package *can* serve a Filament panel to a browser, it *does* serve
   compiled CSS, and axe runs in both colour schemes. ⚠️ Three traps were paid for on the way,
   including that Testbench takes `getPackageProviders()` verbatim so **Livewire must be listed
   last**, or every Filament page 500s. *→ `research.md` R10.*
2. ⚠️ **The blades are a rewrite, not a port.** Every visual class in the source application's
   tree blades is a bare app utility, and none compile from inside `vendor/`. The package owns
   its CSS as `ltree-` prefixed classes in a committed, compiled stylesheet. *→ `AGENTS.md`
   R-019/R-020.*
3. ⚠️ **A guard that has never been watched failing is not a guard**, and a guard that *cannot*
   be made to fail is a finding to investigate rather than a pass. Every story has an explicit
   `Watch … fail` task. *→ constitution Principle I; `AGENTS.md` R-023.*
4. ⚠️ **No public entry point may accept a caller-supplied index.** This is the defect the whole
   package exists to prevent, and `MoveNode` takes an integer position that looks exactly like
   it — `T040` exists to keep that distinction enforced rather than commented.
   *→ constitution Principle II; `contracts/public-api.md`.*

## One thing that is not yours to close — and one that is now done

- **`T099` — the SC-011 screen-reader walk.** Needs a real screen reader and **working audio**.
  If the machine does not have both, leave the task open and report SC-011 **unproved**.

  ⚠️ **DEFERRED to the next release by owner decision, 2026-08-18** — in this package and in the
  consumer application alike. It no longer blocks a release, and it is **still open and still
  unproved**: deferring a proof is not obtaining one. Do not report it as passed, and do not close
  it because `v0.9.0` shipped without it.
  ⚠️ **An accessibility-tree dump and an axe pass are evidence for SC-008, and explicitly not for
  this.** Axe proves a name *exists*; the source application shipped four *correct* ARIA
  assertions while the announced name was wrong.
- **`T100` — the tag. ✅ DONE 2026-08-18: released at `v0.9.0`, repository public.** The gate was
  met in the documented order — the consumer adopted this package, its full suite passed in CI on
  a clean checkout, and its existing audit tests passed **unchanged** — and only then was the tag
  cut. ⚠️ **0.9.0 and not 1.0.0 on purpose**: that one adoption cost **seventeen** public-API
  amendments (PA-1…PA-17), two on the final day, so 0.x keeps the freedom to keep amending without
  a major bump each time. 1.0.0 follows when the surface stops moving.

  ⚠️ **Publishing needed more than the working tree.** History and commit messages named the
  consumer 152 times, and force-pushed objects survive on GitHub — measured, by fetching one back
  after a force-push. The repository had to be **deleted and recreated**, then verified with an
  anonymous clone. If anything is ever scrubbed here again, check the tree, the history, the
  messages **and** the orphans.

## Conventions

- **Generators**: there is no framework scaffolding here — this is a plain composer package, so
  files are hand-written. ⚠️ Do not carry over the parent application's "scaffold via `artisan
  make:` first" rule; it has no application in this repository.
- **Commits**: Conventional Commits, lowercase subject, saying what changed and **why it
  mattered**. Do not commit, push, tag or release unless asked.
- **Reporting**: if tests fail, say so with the output; if a step was skipped, say that. Work
  that was not run is never described as verified. *→ `AGENTS.md` R-038.*

## Relationship to the consumer application

That repository is the **source** of this extraction and the **first consumer** of the result.
It is a private application, checked out beside this repository.

- The extraction record, the five settled decisions, and the traps already paid for:
  its own package notes there.
- ⚠️ **This package's spec numbering is its own.** `specs/001-tree-v1` here is unrelated to that
  repository's `specs/NNN-`. The *adoption* half will be an app slice numbered there.
- ⚠️ **Never run a spec-kit script for this package from a session rooted in that repository.**
  `create-new-feature.sh` resolves the repository from the current directory, so it would create
  the spec — and cut the branch — in the wrong repo.
- Where this repository's documents and the behaviour running in that application disagree, **the
  running behaviour is presumed right** and the disagreement is a finding. It has been wrong
  before. *→ `AGENTS.md` R-040.*

# Validation log — laravel-tree v1

Every `Watch … fail` task records here. A guard whose red has never been seen is
**unverified** (constitution Principle I, `AGENTS.md` R-023), and a guard that
*cannot* be made to fail is a **finding to investigate**, not a pass.

⚠️ **Two kinds of red are recorded separately below, because they prove different
things.**

| Kind | Proves | Does not prove |
|---|---|---|
| **Absence red** — the class under test does not exist yet | the test runs, and reaches the code it names | that the assertion discriminates a right implementation from a wrong one |
| **Mutation red** — the implementation exists and is deliberately broken | the assertion discriminates, and fails for the reason it exists | — |

The source application's lesson is that only the second kind is worth much: two of
its fourteen guards were green against the live defect, and both had been watched
failing in the first sense.

---

## T035 — US1 core guards (T022–T034)

**Absence red observed: 2026-08-14.** Command:

```bash
vendor/bin/pest --testsuite=core
```

```
Tests:    65 failed, 18 passed (47 assertions)
```

The 18 passing were the Phase 1/2 vocabulary and provider suites, which were
already green. All 65 new guards were red, with these messages:

| Count | Message |
|---|---|
| 31 | `Class "Rolland\Tree\Actions\ResolveSiblingPlacement" not found` |
| 12 | `Class "Rolland\Tree\Actions\MoveNode" not found` / `does not exist` |
| 13 | `Class "Rolland\Tree\Actions\PlaceNode" not found` / `does not exist` |
| 9 | `Class "Rolland\Tree\Actions\ReorderSiblings" not found` |

Red files: `ResolveSiblingPlacementTest`, `MoveNodeTest`, `OrderIntegrityTest`,
`EventsTest`, `GenericModelTest`, `PublicSurfaceTest`.

⚠️ **This is absence red only.** Every message is "the class does not exist",
which is the correct failure for a feature that has not been written — but it says
nothing about whether any individual assertion could tell a correct implementation
from a broken one. The mutation section below is what settles that.

---

## T035 (continued) — mutation red for the load-bearing guards

Each mutation below was applied to the finished implementation, the suite was run,
and the implementation was restored. A mutation that failed to redden its guard is
recorded as a **finding**, not quietly dropped.

**Observed: 2026-08-14**, against `83 passed (154 assertions)` on `--testsuite=core`.
Twelve single-point mutations. **Eleven reddened; one did not, and that one is
written up as a finding below rather than passed over.**

| # | Mutation | Guards reddened |
|---|---|---|
| M1 | resolve against the **rendered** list instead of the complete group | 2 — `it resolves against the complete group when a hidden sibling sits before the reference`, `it excludes the node being moved` |
| M2 | drop the unseen-reference refusal | 3 — incl. the `Page` fixture's copy |
| M3 | drop the self-reference refusal | **0 — see finding F1** |
| M4 | swap `Before`/`After` by one | 14 |
| M5 | remove the cycle guard | 4 |
| M6 | remove the `isValidTreeTarget()` guard | 2 |
| M7 | remove the transaction (no atomicity) | 1 — `it leaves the previous order intact when a write fails part-way through` |
| M8 | stop renumbering the group the node **left** | 2 |
| M9 | fire `NodeMoved` once per renumbered sibling | 2 — the exactly-once guards, on both fixtures |
| M10 | stop casting numeric-string keys to `int` (research R4) | 2, both `UnreachableReferenceException` — the exact silent refusal R4 predicts |
| M11 | give `$renderedSiblingIds` a default value | 1 — T041 |
| M12 | add an index-taking public entry point | 2 — T040 |

### ⚠️ Finding F1 — the self-reference refusal reddens under no single mutation

Deleting the explicit `$referenceKey === $node key` check in
`ResolveSiblingPlacement` broke **nothing**. Under `AGENTS.md` R-023 that is a
finding to investigate, not a guard to accept.

**Investigated.** It is not dead code and it is not a redundant guard. Two
independent mechanisms cover the same refusal:

1. `SiblingGroup::for()` excludes the moved node with `whereKeyNot`, so a
   self-reference is not in the complete group and falls through to the
   "not a member" refusal;
2. the explicit self-reference check, which catches it earlier.

Both raise `UnreachableReferenceException` — deliberately, because the three
causes share one type so a caller cannot distinguish "exists but hidden from you"
from "does not exist" (data-model.md § Refusals). So the two paths are
**observationally identical**, and masking either one leaves the other.

Proved by mutating each alone and then both together:

| Mutation | Result |
|---|---|
| drop the self-check only | `83 passed` — nothing reddens |
| drop `whereKeyNot` only | `5 failed` — index-arithmetic guards redden, self-reference does **not** |
| **drop both** | `7 failed` — including `it refuses a reference that is the node being moved` and `GenericModelTest > it refuses a self-reference on the second model` |

**Verdict**: defence in depth on the package's most important rule, correctly
guarded, and reachable — it simply needs a two-point mutation to demonstrate.
Neither mechanism was removed. This entry exists so the next reader does not
"tidy away" one of them on the evidence of a single green mutation run.

---

## Earlier: `IsTreeNode` configurable-column seam

**Mutation red observed: 2026-08-14.** `IsTreeNode::getParentKeyName()` was changed
from `return self::treeParentColumn();` to `return 'parent_id';`.

```
FAILED  Tests\Core\IsTreeNodeTest > it uses the configured parent column for the recursive relationship too
  SQLSTATE[HY000]: General error: 1 no such column: pages.parent_id
```

Exactly one guard reddened — the one written for that seam. The neighbouring guard
(`it reads its column names from configuration`) stayed green, correctly: it
exercises `treeParentId()`, which reads config on a path of its own. Two seams, two
guards, and the mutation hit precisely one.

---

## T055 — US2 bridge guards (T048–T054)

**Absence red observed.** Command: `vendor/bin/pest --testsuite=bridge`

```
Class "Rolland\Tree\Filament\Pages\TreePage" not found
Tests:    17 failed, 2 passed (2 assertions)
```

### ⚠️ Finding F2 — an assertion that was wrong in the defect's own direction

`it applies a move immediately when the host asks for no confirmation` was first
written expecting `[Bravo, Aardvark, Charlie]` — which is what "put Bravo before
Charlie" looks like **if you only count the rows the actor saw**. The correct
answer is `[Aardvark, Bravo, Charlie]`: the complete group reads Aardvark(0),
Charlie(0), Bravo(1), Aardvark wins the name tie-break, so "before Charlie" is
index 1 of the COMPLETE group.

The implementation was right and the test was wrong, in exactly the direction the
071 defect goes. Corrected in place with the reasoning recorded beside it, rather
than quietly adjusted.

### ⚠️ Finding F3 — a race that made a working drag look broken

Four browser drag tests failed while the drag was in fact working. `assertMissing()`
on an element that never appears returns **immediately**, so the test read the
database before the Livewire round trip had landed. The confirmation tests passed
only because `assertPresent()` happened to wait.

Fixed with an explicit `waitForLastChild()` DOM poll — a **wait**, not an
assertion. Every claim is still made against the stored rows.

### ⚠️ Finding F4 — the drag looked broken, and was not

`drag()` on a root node produced no write. Six probes later: the move had gone to
the **confirmation queue**, because the fixture page asks for confirmation on
cross-parent moves. `pendingMove: true`, nothing written — correct behaviour,
asserted wrongly. Recorded because the same symptom (a gesture that "does
nothing") had three different causes during US2, only one of which was a defect.

---

## T062 — styling verified live, in both schemes

⚠️ tasks.md assumed this was **not** assertable, because "the harness serves no
compiled CSS". The R10 spike disproved that for this harness, so `StylingTest.php`
makes the checks for real:

| Probe | Value | Why it can fail |
|---|---|---|
| focus ring `outline-style` | `solid` | default is `none` |
| focus ring `outline-width` | `2px` | default is `0px` |
| row background, light | `rgb(255, 255, 255)` | default is transparent |
| row background, dark | `rgb(39, 39, 42)` | differs from light |
| confirm panel, light / dark | `255,255,255` / `39,39,42` | see F5 |
| live region `display` | not `none` | a hidden region is never announced |

### ⚠️ Finding F5 — a real defect the suite could not have caught

An editing slip spliced the confirmation panel's **light** rule *inside* the
dark-mode media query, leaving `background-color: rgb(255 255 255)` on a dark
page — the confirmation would have been present, focusable and **unreadable**.

Nothing else would have found it: the bridge suite asserts the panel's behaviour,
axe reports no violation for it, and no ordering test touches colour. It is now a
named regression guard.

### ⚠️ Finding F6 — an axe violation that was the HOST's, not the package's

axe reported contrast **4.06** (white on `#477ae3`) against the tree page. The
elements were Filament's own buttons rendering host-supplied row and header
actions, coloured by the `primary` this test fixture had chosen.

The fixture stopped choosing a bad colour. The package was **not** "fixed" by
overriding the host's palette — inheriting the host's accent rather than defining
one is R-021, and overriding it is the one thing it must not do. Worth telling
hosts: laravel-tree cannot rescue a panel whose own primary fails contrast.

---

## T070 — US3 keyboard and ARIA guards (T063–T069)

**Absence red observed** on `--testsuite=browser --filter=KeyboardTraversal`:
`10 failed, 12 passed`.

⚠️ **Several of the 12 "passes" were VACUOUS**, and are recorded because the
number alone is misleading. With no traversal implemented, focus never moved — so
`it moves back up through the displayed rows` and `it ascends to the parent with
Left` passed by asserting that focus was still where it started. Absence red is
even weaker here than it was for US1: a traversal test can pass simply because
nothing happens.

### Mutation red

| # | Mutation | Guards reddened |
|---|---|---|
| K1 | count the TRUE group instead of the rendered siblings | 1 — `it counts only the rendered siblings in the set size` |
| K2 | drop `aria-labelledby`, so the treeitem names itself from its contents | 4 — all three name guards **and** the same-element guard |
| K3 | remove the roving tabindex | 2 — both tab-stop guards *(see F7)* |
| K4 | let arrow traversal wrap | 2 — both no-wrap guards |
| K5 | make Left always ascend, never collapse | 3 |

### ⚠️ T067 — the guard tasks.md said to distrust

T070 warns: *"T067 is the one to distrust — if it passes immediately, the guard is
measuring something downstream of the real guarantee."*

**It did pass immediately.** Investigated with K1, which makes `nodesByParent()`
ignore the search filter so the count describes the true group. That reddened it.

**Verdict**: the guard discriminates. It passed on first run because the design
was already correct — `aria-setsize` is rendered from the already-filtered
grouping, so the count cannot describe rows that were not rendered. The suspicion
was right to raise and the answer is benign, which is only knowable because the
mutation was run.

### ⚠️ Finding F7 — a mutation that does not APPLY looks exactly like a guard that cannot fail

K3 reported "nothing reddened" on its first run. That is the precise signature
`AGENTS.md` R-023 says to investigate — and the cause was not the guard at all:
the `perl` substitution's escaping never matched, so **the mutation was never
applied**. The suite was green because the code was unchanged.

Applied properly, K3 reddens both tab-stop guards.

**The methodological lesson, recorded because it will recur**: before concluding
that a guard cannot be made to fail, confirm the mutation actually landed. A
silent no-op mutation and an unfailable guard produce identical output, and only
one of them is a finding.

### T076 — the empty-accessible-name trap

Filament's own collapsible-section component ships `aria-label=""`, a critical
violation inherited merely by using it. This page uses no collapsible section, so
nothing is currently wrong; two guards were added anyway, to catch the day one is
introduced. An empty `aria-label` is worse than none — it overrides the name the
element would otherwise have computed.

---

## T086 — US4 keyboard reordering guards (T077–T085)

**Absence red observed**: `13 failed, 3 passed`. ⚠️ The three passes were VACUOUS —
with no hold implemented, "writes nothing while held" and "tree unchanged when
cancelled" pass because nothing happens at all.

### Mutation red

Every mutation below **verified that it applied** before the suite ran. That check
exists because of finding F7: a mutation that silently fails to apply is
indistinguishable from a guard that cannot fail.

| # | Mutation | Guards reddened |
|---|---|---|
| R1 | put the node down beside the WRONG neighbour | 1 — `it commits the move when the node is put down` |
| R2 | drop the put-down announcement | 1 — T079, the assertion the source application never wrote |
| R3 | drop the already-last announcement | 1 — T082, the second omission its critique found |
| R4 | call the server on EVERY arrow press | 3 — *after the guard was strengthened; see below* |
| R5 | never abandon a hold when focus leaves the tree | 1 — T081 |
| R6 | remove `wire:ignore` from the live region | **0 — see F9** |

### ⚠️ Finding F8 — a "nothing happened" guard that could outrun the thing it forbids

R4's first form released the hold as a side effect, so only one commit ever
happened and T084 stayed green — an **inadequate mutation**, not a passing guard.
Rewritten to commit on every arrow press while keeping the hold, it reddened T084
immediately.

But `it writes NOTHING to the database while a node is merely held` still passed,
and that WAS a real weakness. The guard asserts that nothing happened, so it has
no signal to wait for — and it was reading the database before a Livewire round
trip that should never have been made could land. The same race as F3, in a form
that hides a defect rather than inventing one.

Fixed with an explicit grace period before the assertion. Re-running the mutation
then reddens three guards instead of two.

**The general lesson**: an assertion that something did NOT happen is only as
strong as the time it waits before looking.

### ⚠️ Finding F9 — the announcement is protected twice, like the self-reference refusal

Removing `wire:ignore` from the live region reddened nothing. Investigated rather
than accepted, and it is the same shape as F1:

| Mutation | Result |
|---|---|
| remove `wire:ignore` only | passes — `x-text="announcement"` re-applies from Alpine state after the morph |
| remove `x-text` only | passes — `wire:ignore` keeps the morph away from the node |
| **remove both** | **fails** — the announcement is wiped, exactly as AGENTS.md R-017 describes |

Both mechanisms are kept. `wire:ignore` is the one the source application's live
defect named, and it must not be "tidied away" on the evidence that the suite stays
green without it.

---

## T095 — quickstart § Verification, run

| Criterion | Command | Result |
|---|---|---|
| SC-010 core stands alone | `composer remove --dev filament/filament` then `pest --testsuite=core` | **83 passed**, `vendor/filament` absent |
| SC-002 / SC-003 ordering + contiguity | `pest --filter=OrderIntegrity` | 8 passed, each in its OWN case |
| SC-004 every refusal | `pest --filter=refuses` | 14 passed |
| SC-005 / SC-007 styling + axe, both schemes | `pest --filter=Styling` | 8 passed |
| SC-006 / SC-008 keyboard + row naming | `pest --filter=Keyboard` | 40 passed |

## T099 — SC-011 is UNPROVED, and cannot be proved on this machine

⚠️ **Reported unproved, not skipped.** The walk needs a real screen reader and
**working audio**, driven by someone who can hear it. Checked rather than assumed:

```
/dev/snd            -> only `timer`; no playback device
aplay, pactl        -> not installed
orca, nvda, jaws,
espeak, spd-say     -> all absent
```

**SC-011 is therefore UNPROVED.** It MUST NOT be inferred from anything already
green:

- the axe passes prove a name **exists**, never that it is sensible — axe cannot
  see a row announcing its whole subtree, because that IS a name;
- the accname assertions prove the **data** a screen reader receives;
- the live-region assertions prove the **text** it would be handed.

None of them prove the announcements work as heard sentences in sequence. The
source application shipped this exact tree with four *correct* ARIA assertions
passing while the announced name was wrong (`AGENTS.md` R-018).

T099 stays **open**.

---

## T097 critique — two Critical findings, fixed

The critique found two requirement gaps that **181 passing tests did not**, both
because nothing tested the requirement at all. This is why tasks.md says to run it
before the branch is finished rather than only at the end.

### C1 — orphan nodes vanished entirely

`nodesByParent()` filed every node under its raw parent key and the blade walked
only from the root group. A node whose PARENT the host's query did not return was
filed under an unreachable key and never rendered.

Evidence before the fix:

```
group keys: [1,""]
roots: ["NormalRoot"]
orphan rendered in HTML: false
```

spec.md § Edge Cases requires the opposite: *"It must render at the root of what
the actor can see without implying its true parent."* A node the actor **was**
permitted to see was invisible, and therefore unreorderable.

Fixed by `treeDisplayRoots()`, which appends orphan groups after the real roots.
⚠️ The display promotes them; the placement rule does **not** — an orphan keeps
its true parent key and is still counted among its real rendered siblings.
Reporting it as "1 of 3" beside the real roots would imply it has no parent, which
is its own disclosure.

**Mutation**: reverting the blade to `$grouped['']` reddens 2 guards.

### C2 — no pointer expand/collapse; FR-024 shipped unmet

`grep` found no click handler in any blade or in `tree.js`. Collapse existed only
via ArrowLeft/ArrowRight, added in **US3** — so FR-024 and US2 acceptance 1, which
belong to **US2**, were unmet, and a mouse-only user could not collapse anything.

Fixed with a click on the chevron alone. ⚠️ It stays `aria-hidden="true"` and
`tabindex="-1"`: the row already announces `aria-expanded`, and a labelled chevron
would announce the same state twice (FR-038, R-016). Hiding a pointer affordance
from assistive technology is right precisely because the keyboard path exists and
is better. The click is on the chevron, not the row — on the row it would make
every row action and every drag start also toggle the branch.

**Mutation**: removing the click handler reddens 2 guards.

### What this says about the earlier task marks

T048–T062 were marked complete before this critique ran, and C2 shows that mark
was wrong: US2 had an unmet functional requirement. The suite was green because
the requirement had no test, not because it was satisfied. **A green suite is
evidence about the tests that exist, and nothing at all about the ones that do
not.**

---

## C3 — the C1 fix made the render N+1, and the suite stayed green

| | queries to render |
|---|---|
| before C1's fix | **1** for 21 nodes |
| after C1's fix | **45** for 21 nodes |
| after C3's fix | **1** for 21 nodes, **1** for 101 |

`treePositionFor()` and `treeSetSizeFor()` are called per row and each re-ran
`nodesByParent()`, which queries; `visibleKeys()` ran it a third time. plan.md
scopes this tree at "hundreds of nodes" — roughly 600 queries for one page.

Fixed by memoising `nodesByParent()` and `searchVisibleIds()` in `protected`
properties, so the cache lives exactly one request and is never serialised into
the Livewire payload. Invalidated by `updatedTreeSearch()` and in a `finally`
after every commit.

### The guards, and why they are shaped as they are

⚠️ **The efficiency guard compares two tree sizes rather than checking a
threshold.** "Fewer than 100 queries" would have passed against BOTH the
one-query render and the forty-five-query one, and caught nothing. Constant-in-N
is the property that matters, so that is what is asserted — plus one absolute cap
to pin the order of magnitude, because a constant 200 would also be wrong. A
second case varies DEPTH rather than breadth, since a recursive include that
queried per level would pass the breadth test.

**Mutations**: un-memoising `nodesByParent()` reddens 3 guards.

### ⚠️ Finding F10 — two staleness guards that could not fail, for two different reasons

The guard on cache invalidation was written twice before it could discriminate.

1. **Read `$page->instance()` after `->call()`** — passed against a page that never
   forgot anything. Livewire builds a **new component instance per request**
   (`spl_object_id` before ≠ after), so the instance being inspected never had its
   cache populated at all.
2. **Assert the HTML the call returned** — also passed. Within a request the write
   happens BEFORE the first render, so the cache is not yet warm when it is
   invalidated.

Only a guard exercising one instance directly — populate, write, re-read — can
fail, and it does, with `the page served the order it held BEFORE its own write`.

⚠️ **What this says about the `finally` block**: on today's code paths nothing
consults `nodesByParent()` before a write, so the invalidation is defensive rather
than currently load-bearing. It stays, because the moment anything before the
write consults it — computing rendered siblings server-side, say — the absence
would silently serve a pre-write tree. This is the third finding of this shape
(F1, F9, F10): correct code whose necessity a single mutation cannot demonstrate.

---

## I2 — a single-node group is now REPORTED, on both surfaces

spec.md § Edge Cases: *"A group contains exactly one node. Every reorder request
is a no-op that must be reported, not silently accepted."*

The keyboard already announced `only_child` while a node was held. The **pointer**
did not: dragging a sole child back onto its own parent silently did nothing — and
still wrote, and still fired `NodeMoved`, recording a move in the host's audit
trail that the user never made.

`TreePage` now detects the case before committing and reports it to **both**
surfaces: the live region (via an `ltree-announce` browser event the controller
listens for) and a Filament notification. Reporting to one alone leaves half the
audience uninformed — a pointer user never hears the live region, and a
screen-reader user should not have to depend on a toast.

⚠️ Scoped to a **reorder**. Being an only child does not make a **re-parent** a
no-op, and a guard broad enough to refuse that would refuse real work.

**Mutations, in both directions** — the second is the one that matters, because a
guard can be wrong by being too eager as easily as by being absent:

| Mutation | Guards reddened |
|---|---|
| remove the report entirely | 4 |
| widen it to catch re-parents too | 1 — `it still moves an only child to a DIFFERENT parent` |

## I3 — `ReorderSiblings` can now address a root-level group

The keys it takes are bare scalars carrying no model class, and the model was
inferred from `$parent`. A root group has none, so a documented public entry point
**threw for an entire class of groups**.

Amended in `contracts/public-api.md` with the reasoning, not patched silently:
a trailing optional `$model`.

⚠️ **Trailing and optional on purpose.** `AGENTS.md` R-030 requires a RENAME when
a signature's *meaning* changes, because a stale positional call would otherwise
stay syntactically valid and silently mean something else. Appending a parameter
moves no existing position, so every call written against the old signature keeps
its exact meaning and no rename is owed. If either of the first two ever changes
meaning, the rename rule applies unchanged.

⚠️ **It refuses rather than guessing.** With no parent and no model there is
nothing to infer from, and a guess would silently reorder some other table's
roots.

**Mutation**: returning a guessed model instead of throwing reddens
`it refuses a root-level reorder that names no model`.

Proved against both fixtures (FR-045), and the root-level path is covered for
ordering, contiguity, the event, and the event's null parent id.

---

## M1 — the CI matrix, and two dependency claims that were not true

Adding PHP 8.5 to the matrix meant first checking which combinations actually
install. They were checked, not assumed, and two of them do not.

### ⚠️ Finding F11 — `staudenmeir/laravel-adjacency-list ^1.26` pins Laravel 13

The dependency is versioned **one Laravel per minor**:

| adjacency-list | `illuminate/database` |
|---|---|
| v1.26.x | `^13.0` **only** |
| v1.24 – v1.25.x | `^12.0` **only** |
| v1.23.5 | `^11.0` **only** |

So `composer.json`'s `^1.26` made `illuminate/* ^11.0|^12.0|^13.0` **unsatisfiable
on anything but Laravel 13** — the package could not have installed on Laravel 12
at all, and CI would have said so on its first run. Widened to `^1.24`, which lets
Composer pick the release matching the host's framework.

Verified rather than assumed: with the constraint widened, Laravel 12 resolves to
framework 12.66 with adjacency-list 1.25.2, and the suites pass on it —
**core 92, bridge 39** on PHP 8.5.

### ⚠️ Finding F12 — Laravel 11 cannot be installed at all

All **108** published `laravel/framework` 11.x releases are affected by unresolved
security advisories. `PKSA-mdq4-51ck-6kdq` alone spans `>=11.0.0,<12.0.0` with no
patched release in that line — Laravel 11 is end-of-life and will not be fixed.
Composer's default `block-insecure` audit therefore refuses to install it.

Consequences, both settled here:

1. the matrix's Laravel 11 legs could **never** have gone green;
2. `composer.json` advertised support for a framework version **no host can
   securely install**.

`^11.0` is dropped from `require`, and Laravel 11 from the matrix.

⚠️ **This contradicts plan.md and the constitution**, which both state
`^11 | ^12 | ^13`. The disagreement is reported rather than papered over —
advertising support for an uninstallable version is a claim, not a capability —
and amending those documents is a `speckit.plan` matter, not a commit.

**Matrix now**: PHP 8.3 / 8.4 / 8.5 × Laravel 12 / 13 × prefer-lowest /
prefer-stable. PHP 8.5 is included because both Laravel legs were **run** against
it locally first.

## M2 — two research decisions amended rather than left to disagree

**R12** said the cycle guard uses `descendantsAndSelf()`. It walks ancestors
instead, because that method comes from the TRAIT rather than the contract: a host
implementing `TreeNode` by hand would silently lose the guard, and a missing cycle
guard corrupts the tree instead of refusing. R12's actual objection — owning
per-driver SQL — does not apply, since the walk owns none. Trade recorded:
O(depth) queries rather than one CTE, cheap at the stated scale.

**R6** said `AlpineComponent::make()`. The bridge ships `Js::make()` with the
controller registering itself on `alpine:init`, because `x-load` never initialised
the component in the package's own served panel — no error, no console output, the
page rendering perfectly and the drag doing nothing.

## M3 — a miscount, and my own misattribution of it

`tasks.md` T093 says there are **five** `[pending artifact]` rules. There are
**four**: R-029, R-031, R-032, R-033.

⚠️ The first correction of this blamed `CLAUDE.md` for the miscount. `CLAUDE.md`
never states a number — the claim is T093's. Both the note in `AGENTS.md` and T093
itself now say so. Recorded because it is exactly the failure R-037 names: a
quoted constraint repeated without checking the artifact it describes.

---

## T096 `/speckit-analyze` — four coverage gaps, and a real defect behind one

### ⚠️ Finding F13 — the accent colour was NEVER inherited (FR-030 / R-021 violated)

The stylesheet shipped `outline-color: rgb(var(--primary-600, 37 99 235))`.

**Filament v5 defines its accent as an oklch() colour** —
`--primary-600: oklch(0.666 0.179 58.318)` — so that expands to
`rgb(oklch(...))`, which is **invalid CSS**. The browser discarded the whole
declaration, the fallback never applied either, and `outline-color` fell back to
`currentColor`. Measured on the untouched page:

```
ring  = oklch(0.141 0.005 285.823)
text  = oklch(0.141 0.005 285.823)   ← identical
```

A focus ring the colour of the text, on **every real install**. The drop-target
border was the same. This is R-021 inverted: not inheriting the host's accent but
ignoring it.

⚠️ **And the guard written to prove inheritance PASSED against it.** The first
version set `--primary-600: 220 38 38` itself and asserted the ring became
`rgb(220, 38, 38)` — supplying the one format that makes the broken expression
valid. It proved the CSS could read a variable in a format nothing uses.

**The lesson, and it is the sharpest one on this branch**: a test that *supplies*
the input which makes the code work proves only that the code works on that input.
The rewritten guards compare the ring against the variable's **actual value read
from the same page**, in whatever syntax the host uses, and additionally assert it
is **not** the text colour — because currentColor is what an invalid declaration
degrades to, and is indistinguishable from having no rule at all.

Fixed by using the variable as a whole colour — `var(--primary-600, rgb(37 99 235))`
— which is valid for any colour syntax a host chooses.

**Mutation**: restoring `rgb(var(...))` reddens all three new guards.

### E2 — the migration stub is now executed

Only its *publishability* had been asserted; the fixtures use migrations of their
own. It is the first command every host runs, and a syntax error in it would have
shipped green. Now published, its `YOUR_TABLE` placeholder rewritten, run, and
rolled back, with the column names read from **config** so the stub and
`config/tree.php` are proved to agree.

**Mutations**: wrong column name → 2 red; `down()` that does not drop → 1 red;
syntax error → 2 red; placeholder hard-coded → 1 red.

⚠️ **One of my own guards was weak and was removed.** A textual check that the
stub "contains `'position'`" stayed GREEN when the created column was renamed to
`positionx`, because the stub's `index(['parent_id', 'position'])` line still
contained the string. It read as stub-versus-config coverage and was a substring
search. Deleted, with the reason recorded in the file.

### E4 — a host override is proved to WIN

FR-019 asks that host copy override the package's. The existing tests asserted
translations *resolve* and lang files are *publishable* — neither is the
requirement. Overrides are now proved to take effect for refusals and
announcements, plus the inverse (the package's own wording when nothing is
overridden, so the guard cannot pass vacuously), plus a sweep asserting no
announcement template holds a placeholder the controller never substitutes.

⚠️ That sweep failed on first run for the wrong reason: `toContain()` in Pest is
**variadic over expected values**, so the failure message passed as a second
argument became a second value the array had to contain. Rewritten as
`expect(in_array(...))->toBeTrue($message)`. Verified failable by injecting a
bogus `:lastx` placeholder.

### F1 / F2 — artifact drift

`plan.md` listed `Concerns/InteractsWithTree.php`, never built (its work is on
`TreePage`; the split would have had nothing on either side of it). Removed, with
the reason. `T096` and `T097` marked complete.

### ⛔ Still open — `AssertsTree`

`contracts/public-api.md` declares `Rolland\Tree\Filament\Testing\AssertsTree` as
**public surface**. It does not exist, and **no task in T001–T100 covers it** — it
was never scheduled. Left open deliberately: building it or retracting it from the
contract is a design decision, and a documented public API that does not exist is
worse than either choice made explicitly.

---

## E1 — `AssertsTree` RETRACTED, and D1 — constitution amended to 1.1.0

### E1 — retracted rather than written

`contracts/public-api.md` declared `Rolland\Tree\Filament\Testing\AssertsTree` as
public surface. It was never built and no task in T001–T100 covered it.

**Retracted**, on this document's own logic: the entry itself described the
helpers as *"explicitly not required — a host may assert against its own markup
instead."* Building them now would mean designing public surface with **zero
consumers**, immediately before the adoption slice that would show what a host
actually needs — the mistake `AGENTS.md` R-035 names for tags, applied to surface
area. Migration: none; nothing was ever published under the name.

⚠️ Two problems were already visible in a draft and are recorded for whenever it
returns: it uses no Filament, so `src/Filament/Testing/` is the wrong home; and it
needs `phpunit/phpunit`, a dev dependency, so it must be test-scoped or declared
in `suggest`.

### D1 — constitution 1.0.1 → **1.1.0**

§ Platform and Toolchain now reads `^12 | ^13` and adjacency-list `^1.24`.

**MINOR, not MAJOR**: no principle was affected — this is the Platform section.
Nothing forbidden became permitted and nothing required became optional. The
amendment block records the principle affected (none), the reason, what is traded
away (support for an EOL framework the project never actually had), and the
migration (none — code, CI and plan were corrected first, when the contradiction
was found by *trying* each combination).

⚠️ **The constitution was the last document still claiming Laravel 11.** Sweeping
for it afterwards found two stragglers the amendment itself would have missed:

- **`README.md` said "Laravel 11, 12 or 13"** — the HOST-FACING claim, and the one
  that matters most. A host would have read it, tried, and been refused by
  Composer's audit with no idea why.
- `tasks.md` T001 still specified the unsatisfiable pairing.

**The lesson**: amending the authority is not the same as fixing the claim. The
claim lives wherever it was repeated, and the copy a consumer reads is rarely the
one under review.

### Also found while sweeping — a dead link in shipped documentation

`README.md` pointed hosts at `specs/001-tree-v1/quickstart.md` for the SC-011
status. `specs/` is **`export-ignore`d**, so that path does not exist in the
distributed package: a broken link for every reader who installed it. The SC-011
status is now stated inline, including that the walk has **not** happened and is
reported unproved rather than inferred from the automated checks.

---

## Laravel 12 dropped — constitution 1.2.0, and the first CI leg ever run

### ⚠️ Finding F14 — a supported version nobody had ever justified

Asked directly: *"why are we using Laravel 12?"* The honest answer was that
**nobody chose it.** The range `^11 | ^12 | ^13` was inherited from the spec, and
the earlier amendment narrowed it only where it was **provably uninstallable** —
which is not the same as justifying what survived.

The deciding fact took one command: the consumer application, the first and
only consumer and the release gate, is on `laravel/framework ^13.17`. Laravel 12
support had **zero consumers**.

Laravel 12 is not broken — verified at **12.61.1 with Filament 5.6.5, Livewire
4.1.0, adjacency-list 1.24, core suite green**. It was dropped anyway, on an
asymmetry: **widening a version range later is MINOR and non-breaking; narrowing
it after publication is breaking.** Nothing is published, so narrow now and grow
when a host actually needs it. `^12.0` was also not honest — everything below
12.61.1 is advisory-blocked.

With Laravel 13 alone, `staudenmeir/laravel-adjacency-list ^1.26` is once again
the correct pin: it requires `illuminate/database ^13.0` only. **The spec's
original value was right for a reason the spec had not established.**

**The general failure mode, recorded because it is not specific to this package**:
a constraint can survive every review by never being the thing under review. It
took a direct question, not an analysis pass, and neither `/speckit-analyze` nor
two critiques had raised it.

### ⚠️ Finding F15 — the prefer-lowest CI leg had NEVER run, and it failed

Narrowing the matrix was the first time `prefer-lowest` was executed at all.
**6 browser tests failed**, on a leg that had been asserted green in three
previous reports on the strength of reasoning alone.

The cause was **not** Laravel or Filament. It was a dev-dependency floor:

```
Call to undefined method Pest\Browser\Api\Webpage::assertNoAccessibilityIssues()
```

`pestphp/pest-plugin-browser` was constrained `^4.0`, and the method arrives in
**v4.1.0** — checked against the tags rather than guessed. Every `prefer-lowest`
leg would have failed in CI, in a way no amount of local `prefer-stable` running
could ever have shown.

Raised to `^4.1`. The floor now resolves to Laravel 13.12.0, Filament 5.6.5,
Livewire 4.2.0, Pest 4.3.2, plugin 4.1.0 — **215 passed**.

⚠️ **The lesson is about the earlier reports, not the fix.** "The matrix is
correct" was stated three times from reasoning. Running it took one command and
found a real defect immediately. A claim about CI that CI has never checked is a
claim.

### Matrix now

PHP 8.3 / 8.4 / 8.5 × prefer-lowest / prefer-stable — **6 legs**, down from 12,
with no Laravel axis. 8.3 is the floor every runtime dependency declares
(`laravel/framework` 13.x, adjacency-list 1.26 and testbench 11 all require
`^8.3`), verified against the published constraints rather than assumed.

**Both floors are now proven locally**: prefer-lowest 215 passed, prefer-stable
215 passed, core-without-filament 101 passed.

---

## ⚠️ Finding F16 — CI's first run failed all six matrix legs

The branch was pushed and CI executed for the very first time.

| Job | Result |
|---|---|
| phpstan | ✅ |
| pint | ✅ |
| core suite · filament uninstalled | ✅ |
| all 6 × PHP × dependency-version legs | ❌ **failed** |

```
Pest\Browser\Exceptions\PlaywrightNotInstalledException
Playwright is not installed. Please run [npm install playwright && npx playwright install]
```

The workflow ran `composer test`, which includes the **browser** suite, on runners
with no Node and no Chromium. ⚠️ **`CLAUDE.md` documents this requirement in so
many words** — *"the browser suite needs Playwright"* — and the workflow I wrote
never honoured it. The requirement was written down and not followed, by the same
author, in the same repository.

**Fixed by splitting the browser suite into its own job** rather than installing
Chromium six times to exercise the same PHP over HTTP:

- the matrix legs run `--testsuite=core,bridge` (**140** tests);
- a dedicated `browser` job installs Node and Chromium and runs
  `--testsuite=browser` (**75** tests).

The union is `composer test` (**215**), which is still what a developer runs
locally. Every command was verified locally before pushing again, rather than
guessing twice.

The browser job also gates the **committed bundle**: it runs `npm run build` and
fails if `resources/dist/` changes, because a stale committed bundle ships broken
code that every other check reports as green (Principle VI). ⚠️ That gate was
itself verified failable — appending a rule to `resources/css/tree.css` makes it
fire.

**The lesson is the same one F15 taught, one commit earlier and evidently not yet
learnt**: three reports had described this CI as correct without it ever having
run. Two consecutive findings now have the same root — a claim about an automated
check that the automated check had never made.

---

## PA-1…PA-4 — four amendments requested by the first real consumer

**Date: 2026-08-17.** Requested in the consumer's adoption slice
(`contracts/package-amendments.md`, research F1/F3/F4/F10) and landed here as
design amendments to `contracts/public-api.md` per `AGENTS.md` R-005.

⚠️ **The finding rate is the release gate working, not a problem with it.** Four
blocking amendments — one of them a security hole — were found by building the
first consumer against an untagged package. That is exactly what R-035 exists for.

Baseline before any change: **215 passed**. After all four: **248 passed**,
PHPStan level 8 clean, Pint clean.

### Red observed, per amendment

| Amendment | Command | Red |
|---|---|---|
| PA-1 | `pest --testsuite=bridge --filter=InteractsWithTree` | **9 failed** — `Trait "Rolland\Tree\Filament\Concerns\InteractsWithTree" not found` |
| PA-2 | `pest --testsuite=bridge --filter=AuthorizeTreeMove` | **7 failed, 2 passed** |
| PA-3 | `pest --testsuite=bridge --filter=ReorderRouting` | **3 failed, 6 passed** |
| PA-4 | `pest --testsuite=browser --filter=AnnouncementPlaceholders` | **4 failed, 1 passed** |

PA-1's red is **absence red only** — the trait did not exist, so every test in the
file errored at autoload. PA-2, PA-3 and PA-4 are stronger: the code existed and
ran, and the reds are the assertions discriminating.

### ⚠️ Finding F17 — PA-1 as requested would have fatalled on the host it was for

PA-1 asked for a trait "carrying the current body". Carrying **all** of it does not
compose. `TreePage` declares `protected static string $model`, and a trait property
against a using class's own property with a different initial value is a fatal
composition error, not a warning:

```
C and T define the same property ($model) in the composition of C.
However, the definition differs and is considered incompatible.
```

Every host names its own model, so a trait carrying `$model` would refuse to
compose with **every** host — including the resource-page host the amendment
exists to enable. Probed directly rather than reasoned about; the same probe
confirmed the two rules the amendment depends on:

| Composition | Result |
|---|---|
| trait property vs **class's own** property, different default | ⛔ fatal |
| trait property vs **parent's** property, different default | ⛔ **THIS ROW WAS WRONG — see F21.** Allowed on PHP 8.5 only; fatal on 8.3 and 8.4 |
| trait method vs **inherited** method | ✅ trait wins — so `getHeaderActions()` reaches the host slot |

`$model` and `treeModel()` therefore stay on `TreePage`, reached by inheritance.
Nothing in the trait reads them.

### ⚠️ Finding F18 — research F1's stated mechanism is wrong; its conclusion is not

F1 records the two Filament page classes as *"siblings under `BasePage`"*. They are
not. `Filament\Resources\Pages\Page` does `use Filament\Pages\Page as BasePage` and
extends it, so the resource page is a **descendant** of the panel page:

```
Filament\Pages\BasePage ← Filament\Pages\Page ← Filament\Resources\Pages\Page
```

The conclusion survives unchanged — `route()` is declared only on the child
(`Resources/Pages/Page.php:136`, not `:125` as recorded), and extending `TreePage`
lands a host on the parent, where it does not exist. Inheritance runs one way.

Recorded per `AGENTS.md` R-037: the claim was load-bearing for an amendment, so it
was checked against the artifact rather than repeated. ⚠️ Both drifted details are
in the direction of sounding *more* authoritative than the reading supported.

### ⚠️ Finding F19 — one PA-2 guard cannot be made to fail, by construction

`it('never asks the host about a node the actor cannot see')` passed **before**
PA-2 was implemented, vacuously: the hook did not exist, so it was never called
with anything. After implementation it is meaningful but still cannot be mutated
into failing — `authorizeTreeMove(Model $node, …)` requires a resolved model, so
there is no way to call it earlier than resolution without changing its signature.

Left in place rather than deleted. It is the third guard in this log held by
structure rather than by a check, and the rule stands: **do not tidy it away on
the evidence that the suite stays green without it.**

### PA-3 — one existing guard re-pointed, and why that is not weakening it

`KeyboardReorderTest`'s FR-041 guard asserted `MoveCounter::$moved === 1` after a
keyboard reorder. A keyboard reorder never leaves its group, so under PA-3 it fires
`SiblingsReordered` and the guard went red — **the correct consequence**, observed:

```
it fires exactly one move event however many keystrokes produced it
Failed asserting that 0 is identical to 1.
```

Re-pointed to `$reordered === 1` **and** `$moved === 0`. The guarantee — one event
per completed interaction, however many keystrokes — is unchanged and now proved on
the correct event, with the wrong event proved absent. The counters are kept
separate deliberately: a counter that summed them could not tell one reorder from
one move, which is the distinction PA-3 exists to restore.

### PA-3 — a deliberate second copy of the invalid-target guard

`MoveNode` refuses a destination whose `isValidTreeTarget()` is false;
`ReorderSiblings` asks nobody. Routing reorders around `MoveNode` would therefore
have **started allowing** reorders inside a parent the host froze — a silent
widening of what is permitted that no consumer asked for. The reorder path asks the
same question before writing, and `MoveNode` keeps its own for direct callers.

Pinned by `it('still refuses a reorder inside a parent the host says cannot receive
children')`, which was green before the change and stays green after. ⚠️ That is
the point: it is a **behaviour-preservation** guard, so its value is that it never
went red, and it is a fourth instance of the two-mechanism shape above.

### ⚠️ Finding F20 — `moved_into` ships as a template with no producer

While writing PA-4's per-key placeholder table:

```
$ grep -oE "say\('[a-z_]+'" resources/js/tree.js | sort -u
say('abandoned' say('already_first' say('already_last' say('cancelled'
say('moved' say('only_child' say('picked_up' say('put_down' say('refused'
```

Nine keys. `announce.moved_into` is a tenth in `lang/en/tree.php`, documented and
translatable, and **nothing anywhere announces it** — the controller never says a
re-parent is "inside :parent". This is the same shape as the `SiblingsReordered`
gap PA-3 closed, found the same way.

**Not fixed**, and deliberately so: PA-4's scope is the placeholder contract, and
the consumer's own F10 records `moved_into` as *"a tenth announcement this app has
never had"*. Inventing when it fires would be designing announced copy with no
consumer — the mistake R-035 names for tags, applied to wording. Recorded in the
contract table as having no producer so the next reader cannot mistake it for
working surface.

### SC-011 remains UNPROVED

Nothing in PA-1…PA-4 changes that. PA-4 makes the announcements *carry the right
data*, proved by reading the live region's text — which is evidence for the
placeholder contract and explicitly **not** for SC-011 (`AGENTS.md` R-018). ⚠️ PA-4
in fact widens what a screen-reader walk would need to cover, since four
announcements now say more than they did.

---

## PA-5 — `matchesSearch()` promoted to the documented member table

**Date: 2026-08-17.** The cheapest amendment in the set, and the only one whose red
had to be a **mutation** red rather than an absence red — the slot already worked,
so nothing could fail for want of an implementation. What needed proving was that
the search path still **asks** the host.

**Mutation applied** — bypass the slot inside `readSearchVisibleIds()`, comparing
the tie-breaker column inline, which is what the default `matchesSearch()` does
anyway:

```
Tests:    2 failed, 4 passed (10 assertions)
⨯ it honours a host that searches a column the package knows nothing about
⨯ it keeps a match found through the host column reachable by showing its ancestors
```

The four that stayed green are the right four: the reflection check, the
default-behaviour check, the non-vacuity check and the privacy check do not depend
on the slot being consulted. Mutation reverted and confirmed byte-identical to
`HEAD` before continuing.

⚠️ **The fixture needed a second searchable column to make this provable at all.**
`categories` gained a nullable `short_code`, because with `name` as both the
tie-breaker and the only text column, a package that ignored the host's override
would still find every row by name and pass. This is `AGENTS.md` R-025 in its
original form: a fixture must be shaped so a correct and an incorrect
implementation produce **different** output.

Suite after: **254 passed** (248 + 6), PHPStan level 8 clean, Pint clean.

⚠️ **Why a documentation-only amendment was worth a guard.** This contract's own
first line says anything unlisted "may change without a major version" — so the
consumer's override was a private dependency on an internal, and a patch release
could have broken its search silently. That is the same class of defect as PA-2's
false claim, in the opposite direction: there the document promised more than the
code did, here it promised less than the code was relied on for.

---

## ⚠️ Finding F21 — the first CI run of PA-1…PA-5 failed 5 of 10 jobs, at COMPILE time

**Date: 2026-08-17.** Run `32003369469`, the first push of the amendment set.

```
success  phpstan                                    failure  browser suite · playwright
success  pint                                       failure  PHP 8.4 · prefer-stable
success  PHP 8.5 · prefer-stable                    failure  PHP 8.4 · prefer-lowest
success  PHP 8.5 · prefer-lowest                    failure  PHP 8.3 · prefer-stable
success  core suite · filament uninstalled          failure  PHP 8.3 · prefer-lowest
```

One root cause behind all five:

```
PHP Fatal error: Filament\Pages\Page and Rolland\Tree\Filament\Concerns\InteractsWithTree
define the same property ($view) in the composition of
Rolland\Tree\Filament\Pages\TreePage. However, the definition differs and is
considered incompatible.
```

### What went wrong, precisely

F17 above records a probe of PHP's trait-composition rules, run while extracting
the trait. It got the `$model` case right. It got this row **wrong**:

> trait property vs **parent's** property, different default → ✅ allowed

That is true on **PHP 8.5**, which is the only PHP on this machine. On **8.3 and
8.4 it is a fatal error**, and `composer.json` supports all three. The probe was
run once, on one version, and its result was written down as *a rule of the
language*.

⚠️ **This is F15/F16's lesson for the third time**: a claim about behaviour that
the check capable of contradicting it had never been run against. The two earlier
instances were about CI never having executed; this one is about CI never having
executed **the versions the matrix exists to cover**. The local suite — 254 tests,
PHPStan level 8, Pint — is incapable of seeing it, because a composition error is
raised when the class is *compiled*, so no assertion inside it ever runs.

⚠️ **It also means PA-1 as first committed was broken on two of three supported PHP
versions**, and every local signal was green. `962042f` through `e34a53d` should be
read with that in mind.

### The fix

`BasePage::render()` calls `view($this->getView(), ...)` and `getView()` returns
`$this->view`. So the trait now overrides the **method** and declares no property:

```php
public function getView(): string
{
    return 'tree::tree';
}
```

A trait METHOD beats an INHERITED one on every supported version — that half of the
original probe was correct and is unaffected.

### The guard, and why it is general

`tests/Bridge/TraitCompositionTest.php` does **not** check `$view`. It asserts the
trait declares **no property that any Filament page ancestor declares**, by
reflecting over `BasePage`, `Pages\Page` and `Resources\Pages\Page`. A property
added to this trait in future is therefore caught on **any** PHP version, rather
than on two thirds of the matrix.

**Mutation red observed locally on PHP 8.5** — the version that permits the
construct — before the fix:

```
⨯ it declares no trait property that a Filament page ancestor also declares
  trait property collides with a Filament page property: view
⨯ it reaches the tree view through getView(), which is a method and composes safely
Tests: 2 failed, 3 passed
```

⚠️ That is the point of shaping the guard as a reflection check rather than a
behavioural one: **it reproduces a fatal that this machine's PHP cannot raise.**
A behavioural test would have passed here and failed only in CI.

Suite after: **259 passed** (254 + 5), PHPStan level 8 clean, Pint clean. ⚠️ The
8.3/8.4 verdict is CI's to give, not this machine's — recorded as unproven locally
until that run reports.

---

## ⚠️ Finding F22 — `ReorderSiblings` documented a `list` it did not enforce

**Date: 2026-08-17.** Found while adopting the package, from a **larastan error in
the consumer** — not from anything in this repository's own suite.

The consumer's audit listener wrapped the event payload defensively:

```php
->withProperties(['ordered_ids' => array_values($event->orderedKeys)])
```

larastan flagged it:

```
Parameter #1 $array (list<int|string>) of array_values is already a list,
call has no effect.
```

⚠️ **That is true only if the promise holds, and it did not.** `handle()` documents
`@param list<int|string> $orderedKeys`, and nothing enforced it:

- `array_map` **preserves keys**, so `$normalised` inherited whatever keys arrived;
- the write loop used `foreach ($normalised as $index => $key)` — **the array key IS
  the position**;
- `SiblingsReordered` then carried that keyed array while its own docblock promised
  a list.

So a caller passing `[3 => $a, 7 => $b, 9 => $c]` had **3, 7 and 9 written as
positions** — a non-contiguous group, in breach of `AGENTS.md` R-009, from a
documented public entry point. And the event serialised to a JSON **object**, so a
host storing `ordered_ids` in an audit trail got `{"3":12}` where every consumer
expected `[12]`.

⚠️ **Reachable by an ordinary mistake, not a contrived one.** `array_filter`
preserves keys. A host filtering a list before reordering it is the obvious path,
and the failure is silent in both directions.

**Red observed** (2 of 11 in `ReorderSiblingsTest`):

```
⨯ it writes contiguous positions when the caller passes a KEYED array
    Failed asserting that two arrays are identical.  (positions were [3,7,9])
⨯ it emits a real list on SiblingsReordered even from a keyed call
    Failed asserting that false is true.             (array_is_list)
```

**Fixed at the source** — `array_values(array_map(...))` — rather than defended
against in every caller. The consumer's listener now trusts the documented type and
its `array_values` is gone, which is what larastan was asking for.

Suite after: **261 passed** (259 + 2), PHPStan level 8 clean, Pint clean.

⚠️ **Same defect SHAPE as PA-2**, from the opposite direction: PA-2 was a contract
promising a check the code did not perform; this was a signature promising a type
the code did not honour. Both were found by a consumer, and neither could be found
by reading this repository alone — R-037's "a quoted constraint is a claim" applies
to **type annotations**, not only to prose.

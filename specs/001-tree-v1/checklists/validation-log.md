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

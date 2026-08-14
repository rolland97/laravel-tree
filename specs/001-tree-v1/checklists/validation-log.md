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

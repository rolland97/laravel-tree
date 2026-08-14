# Phase 1 Data Model — laravel-tree v1

The package owns **no tables**. This document describes what a host model must provide, what the
package writes back, and the shapes that cross the boundary.

---

## The host's columns

Two attributes on the host's existing table. Names are configurable; the defaults are shown.

| Attribute | Default name | Type | Meaning |
|---|---|---|---|
| Parent reference | `parent_id` | nullable FK to the same table | `null` means the node is a root |
| Position | `position` | unsigned integer, not null, default 0 | Order among siblings sharing a parent |

⚠️ **Position is meaningful only within a sibling group.** It is not globally unique, and the
package never treats it as an identifier. After any move the package guarantees the affected
group holds a contiguous, collision-free sequence starting at 0 — but it makes **no guarantee
about groups it did not touch**, which is why the read order tie-breaks (see below).

The schema change ships as `database/migrations/add_tree_columns.php.stub`. The host publishes
it, names its own table, and runs it. ⚠️ It is a **stub**, not an auto-discovered migration —
Principle III.

## Read order

A group is always read as: **position ascending, then the configured tiebreaker ascending.**

The tiebreaker exists because real data violates the contiguity guarantee — legacy rows, rows
written before the package was adopted, and rows in groups no move has yet touched. Without it,
two nodes sharing a position would order arbitrarily by driver, and the index the package
resolves would mean something different from what the user saw.

⚠️ **Testing consequence, carried from a live defect**: because the read tie-breaks by name, a
*wrong* implementation and a *right* one produce the same visible order for a carelessly-named
fixture. Fixtures used to prove ordering MUST be named so the two differ (spec FR-048).

---

## Contracts the host implements

### `TreeNode`

What the package needs to know about a node, and nothing more.

| Member | Answers | Notes |
|---|---|---|
| `getKey()` | the node's identity | Eloquent already provides it |
| `treeParentId()` | the parent's key, or `null` | Reads the configured column |
| `treePosition()` | the position among siblings | Reads the configured column |
| `isValidTreeTarget()` | may this node receive children? | ⚠️ **The host's decision, not the package's.** The source application answers "is it active?"; another host might answer "is it not archived?" or always `true` |

`IsTreeNode` supplies default implementations of the first three plus the recursive
relationships, so a host normally implements only `isValidTreeTarget()`.

⚠️ **Visibility is deliberately absent from this contract.** It is not a property of a node; it
is a property of a node *and an actor*. Putting it here would invite a single global answer, and
the package would then have a default — which fails in the unsafe direction. It arrives as an
argument instead.

---

## Shapes that cross the boundary

### `SiblingPlacement`

| Case | Needs a reference? | Meaning |
|---|---|---|
| `Before` | yes | Immediately before the reference, among the reference's siblings |
| `After` | yes | Immediately after it |
| `LastChild` | **no** | Last among the destination parent's children |

`LastChild` names no neighbour, which is why it was already correct in the source application
before 071 — there was no index to get wrong.

### The two sibling lists

⚠️ **The distinction this package exists to preserve. Conflating them is the defect.**

| Name | Contents | Supplied by |
|---|---|---|
| **Complete group** | Every child of the destination parent, excluding the node being moved, in read order | The package, by querying |
| **Rendered siblings** | The subset the actor could actually see, in display order | The **caller**, as an argument |

The resolved index is always an index into the **complete** group. The rendered list is used for
one purpose only: refusing a reference the actor was never shown.

---

## Events

Fired after a successful write, inside the transaction's commit.

| Event | Carries |
|---|---|
| `NodeMoved` | the node, previous parent key, new parent key, new position |
| `SiblingsReordered` | the parent key (nullable for roots) and the ordered keys as written |

The package writes no audit record. A host that wants one listens.

---

## Refusals

Each is a distinct exception type, because a host reacts differently to each (spec FR-013).

| Exception | Raised when |
|---|---|
| `CycleException` | the destination is the node itself or one of its descendants |
| `InvalidTargetException` | the host answered `false` to `isValidTreeTarget()` |
| `UnreachableReferenceException` | the reference is not in the complete group, was not among the rendered siblings, or is the node being moved |

⚠️ **The three `UnreachableReference` causes share one type on purpose.** They are one fact from
the caller's perspective — *you named something you could not have aimed at* — and separating
them would let a caller distinguish "this node exists but you cannot see it" from "this node
does not exist", which is precisely the disclosure FR-037 prevents elsewhere.

---

## Configuration

`config/tree.php`, publishable:

| Key | Default | Purpose |
|---|---|---|
| `parent_column` | `parent_id` | Host's parent reference |
| `position_column` | `position` | Host's ordering attribute |
| `tiebreaker` | `name` | Second sort key; see Read order |

⚠️ Configuration is **global to the package**, not per-model, in v1. A host with two tree models
using different column names is out of scope and should be reported as a limitation rather than
worked around — if it turns up, it is a `MINOR` amendment, not a patch.

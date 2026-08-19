# Public API Contract — laravel-tree v1

⚠️ **This document is the whole public surface. Anything not listed here is internal and may
change without a major version** (constitution Principle III's analogue for this package;
`AGENTS.md` R-005). Additions come from a design amendment, not from a commit.

Signatures below are the contract. Bodies are not.

---

## Core — available with Filament absent

### `Rolland\Tree\Contracts\TreeNode`

```php
interface TreeNode
{
    public function getKey();

    public function treeParentId(): int|string|null;

    public function treePosition(): int;

    /**
     * May this node receive children? The HOST decides — the package has no opinion.
     */
    public function isValidTreeTarget(): bool;
}
```

### `Rolland\Tree\Concerns\IsTreeNode`

A trait supplying `treeParentId()`, `treePosition()`, and the recursive relationships from
`staudenmeir/laravel-adjacency-list`. It does **not** supply `isValidTreeTarget()`: a default
there would be the package deciding, and every host that forgot to implement it would silently
inherit that decision.

### `Rolland\Tree\Enums\SiblingPlacement`

```php
enum SiblingPlacement
{
    case Before;
    case After;
    case LastChild;

    public function needsReference(): bool;
}
```

### `Rolland\Tree\Actions\ResolveSiblingPlacement`

```php
final class ResolveSiblingPlacement
{
    /**
     * Turn "place this node beside that sibling" into an index within the COMPLETE
     * destination group.
     *
     * @param  list<int|string>  $renderedSiblingIds  the destination group AS THE ACTOR SAW IT,
     *                                                in display order
     * @return int  index within the complete group, the moved node excluded
     *
     * @throws UnreachableReferenceException
     */
    public function handle(
        Model&TreeNode $node,
        ?TreeNode $destinationParent,
        ?TreeNode $reference,
        SiblingPlacement $placement,
        array $renderedSiblingIds,
    ): int;
}
```

⚠️ **`$renderedSiblingIds` is not an optimisation and MUST NOT be given a default.** An empty
default would silently disable the check that a reference was visible, turning a security
refusal into a no-op. A caller that genuinely renders everything passes everything.

### `Rolland\Tree\Actions\MoveNode`

```php
final class MoveNode
{
    /**
     * Re-parent and/or re-position. The subtree follows. Atomic. Fires NodeMoved once.
     *
     * @throws CycleException|InvalidTargetException
     */
    public function handle(Model&TreeNode $node, ?TreeNode $newParent, int $position): void;
}
```

⚠️ **`$position` here is an index the package itself produced**, via `ResolveSiblingPlacement`.
It is *not* a client-supplied index, and this is the one place the distinction is easy to lose:
a caller that computes this integer any other way has reintroduced the defect the package exists
to prevent. Callers outside the package should prefer `PlaceNode` below.

### `Rolland\Tree\Actions\PlaceNode`

```php
final class PlaceNode
{
    /**
     * The composed entry point: resolve, then move. This is what hosts should call.
     *
     * @param  list<int|string>  $renderedSiblingIds
     *
     * @throws CycleException|InvalidTargetException|UnreachableReferenceException
     */
    public function handle(
        Model&TreeNode $node,
        ?TreeNode $destinationParent,
        ?TreeNode $reference,
        SiblingPlacement $placement,
        array $renderedSiblingIds,
    ): void;
}
```

⚠️ **Named `PlaceNode`, never `DropNode`, and this naming is load-bearing.** In the source
application the equivalent Livewire method was renamed `dropNode` → `placeNode` **as the safety
mechanism**: its parameters reordered, so a stale positional call would have stayed
syntactically valid and silently meant something else. Named arguments do **not** catch that —
Livewire dispatches through the container's method injection, which matches by name and
**silently discards unknown keys**; a probe passing two arguments the method did not declare ran
clean on defaults. A *renamed* method throws before dispatch. If this signature ever changes
again, **rename it again** (`AGENTS.md` R-030).

### `Rolland\Tree\Actions\ReorderSiblings`

```php
final class ReorderSiblings
{
    /**
     * Rewrite one group's order to the given keys. Fires SiblingsReordered once.
     *
     * @param  list<int|string>  $orderedKeys  the COMPLETE group, in the desired order
     * @param  class-string<Model&TreeNode>|null  $model  required only when $parent is null
     *
     * @throws InvalidArgumentException when a root-level group names no model
     */
    public function handle(?TreeNode $parent, array $orderedKeys, ?string $model = null): void;
}
```

⚠️ **AMENDMENT (during implementation, 001-tree-v1).** `$model` was added because
the signature as first written could not address a **root-level group at all**.
The ordered keys are bare scalars carrying no model class, and the model was
inferred from `$parent` — which a root group does not have. That made a documented
public entry point throw for an entire class of groups.

**Why a trailing optional parameter rather than a rename.** `AGENTS.md` R-030
requires a *rename* when a signature's **meaning** changes, because a stale
positional call would otherwise stay syntactically valid and silently mean
something else. Appending a parameter moves no existing position, so every call
written against the previous signature keeps its exact meaning. ⚠️ If either of
the first two parameters ever changes meaning, **rename the method** — that rule
is unchanged by this amendment.

**Why it refuses rather than guessing.** With no parent and no model there is
nothing to infer from, and a guess would silently reorder some other table's
roots. Refusing is the safe direction.

### Events

```php
final class NodeMoved
{
    public function __construct(
        public readonly Model&TreeNode $node,
        public readonly int|string|null $previousParentId,
        public readonly int|string|null $newParentId,
        public readonly int $position,
    ) {}
}

final class SiblingsReordered
{
    /** @param list<int|string> $orderedKeys */
    public function __construct(
        public readonly int|string|null $parentId,
        public readonly array $orderedKeys,
    ) {}
}
```

### Exceptions

```php
CycleException                 extends DomainException
InvalidTargetException         extends DomainException
UnreachableReferenceException  extends DomainException
```

---

## Filament bridge — present only when Filament is installed

### `Rolland\Tree\Filament\Concerns\InteractsWithTree`

```php
trait InteractsWithTree
{
    // The whole tree body: the host slots below, the reads the view calls, and
    // the Livewire entry points placeNode() / confirmPendingMove() / cancelPendingMove().
}
```

Usable on **any** Filament page class:

```php
class TreeVendorCategories extends Filament\Resources\Pages\Page
{
    use Rolland\Tree\Filament\Concerns\InteractsWithTree;

    protected function visibleQuery(): Builder { /* the host's privacy scope */ }
}
```

⚠️ **AMENDMENT (during implementation, 001-tree-v1).** The tree was reachable only by
extending `TreePage`, and that made an entire class of host **impossible**. Requested by
the first real consumer (the consumer's adoption, research F1) as **PA-1**.

**What was impossible.** `route()` is declared **only** on
`Filament\Resources\Pages\Page`. A tree registered as a resource's index page —
`VendorCategoryResource::getPages()` → `'index' => TreeVendorCategories::route('/')` — must
extend that class, and PHP has no second inheritance slot with which to also reach
`TreePage`. Forcing the host to choose meant giving up the resource's index URL,
`getUrl()`, the breadcrumb and the sub-navigation, or giving up the package.

⚠️ **F1's stated mechanism is wrong; its conclusion is not.** F1 records the two page
classes as *"siblings under `BasePage`"*. They are not — `Resources\Pages\Page extends
Filament\Pages\Page`, so the resource page is a **descendant** of the panel page. The
conclusion survives unchanged, because inheritance runs one way: `route()` lives on the
child, and extending `TreePage` lands a host on the parent, where it does not exist.
Recorded rather than repeated, per `AGENTS.md` R-037.

**Why a trait rather than a second base class.** Two base classes would be two copies of
the placement contract, and the resource-page one would be the copy that silently fell
behind. `TreePage` is now a thin class over this trait, so both hosts run the same body.

⚠️ **`static $model` did NOT move into the trait, and this is load-bearing.** A trait
property and a using class's own property with **different initial values** is a fatal
composition error:

```
C and T define the same property ($model) in the composition of C.
However, the definition differs and is considered incompatible.
```

A trait carrying `protected static string $model` would therefore refuse to compose with
every host that names its model — which is all of them. `$model` and `treeModel()` stay on
`TreePage`, where they are reached by *inheritance*, which permits a differing default.
Nothing in the trait reads them. **A host using the trait directly declares its model
however it likes and does not get `treeModel()`.**

⚠️ **`getHeaderActions()` collides, and the collision is checked rather than assumed.** It
is `protected` on `Filament\Pages\Concerns\InteractsWithHeaderActions`, which both base
pages use, and also on this trait. PHP resolves a **trait** method ahead of an **inherited**
one, so the trait wins and the host's `headerActions()` slot is reached — asserted in
`tests/Bridge/InteractsWithTreeTest.php`, because a wrong answer here surfaces to a host as
a silently missing toolbar rather than as an error.

**Migration**: none. `TreePage` keeps its name, its members and its behaviour.

### `Rolland\Tree\Filament\Pages\TreePage`

Abstract, and since PA-1 a thin class over `InteractsWithTree`. A host extends it and
implements or overrides:

| Member | Required? | Purpose |
|---|---|---|
| `static string $model` | **yes** | The tree model |
| `visibleQuery(): Builder` | **yes** | ⚠️ The host's privacy scope. The package never adds one |
| `badgesFor(Model $node): array` | no | Row badges |
| `rowActions(Model $node): array` | no | Row actions |
| `headerActions(): array` | no | Page-level actions |
| `leafSlot(Model $node): ?View` | no | Non-node rows beneath a node |
| `confirmationFor(Model $node, ?TreeNode $newParent): string\|array\|null` | no | Return a warning to require confirmation; `null` applies immediately. ⚠️ A string is the body under this package's heading; `['heading' => …, 'message' => …]` names the question itself (PA-6) |
| `authorizeTreeMove(Model $node, ?TreeNode $newParent): bool` | no | ⚠️ The host's **permission** rule. Defaults to `true` |
| `matchesSearch(Model $node, string $term): bool` | no | Which columns a quick search looks in. Defaults to the tie-breaker |
| `treeEmptyMessage(): string` | no | Which empty-state sentence to show. Chooses on whether a search is active |
| `treeStrings(): array` | no | Override the announcement templates |
| `treeAccessibleName(): string` | no | ⚠️ The **tree's own** accessible name. Defaults to the navigation label |
| `treeBranchesStartCollapsed(): bool` | no | Do branches start closed? Defaults to `false` — open, as before the slot existed |
| `canMoveNode(Model $node): bool` | no | ⚠️ May THIS ACTOR move this node? A keyboard **courtesy**; defaults to `true` |
| `treeReorderEnabled(): bool` | no | ⚠️ Does this PAGE offer ordering at all? Defaults to `true`. A false answer **removes the affordance** rather than refusing it (PA-18) |

Public Livewire entry points on the page: `placeNode(...)`, `confirmPendingMove()`,
`cancelPendingMove()`.

⚠️ **`placeNode()` re-runs the host's `visibleQuery()` on the committing call**, and — since
PA-2 — asks `authorizeTreeMove()` there too, regardless of any check made for presentation.
The keyboard refuses a pick-up early as a *courtesy*; that refusal is not the guard.

⚠️ **PROMISED ORDER of the confirmation path** — ⚠️ AMENDMENT (during implementation,
001-tree-v1), requested by the first consumer:

1. `placeNode()` resolves the node and destination through `visibleQuery()`;
2. it asks `authorizeTreeMove()`;
3. it calls `confirmationFor()` **exactly once**, and only from here — never from
   `confirmPendingMove()` or `commit()`;
4. only then does it set `$pendingMove`.

**This ordering is part of the contract, not an implementation detail.** A host that needs
structured confirmation data — or needs to dispatch its own modal events — can wrap
`placeNode()` and `confirmPendingMove()` with trait aliasing, memoise what it needs inside
`confirmationFor()`, and add its own keys to `$pendingMove` afterwards:

```php
use InteractsWithTree {
    placeNode as private packagePlaceNode;
    confirmPendingMove as private packageConfirmPendingMove;
}
```

⚠️ This works only because PA-1 made the tree a trait — a base class cannot be aliased. It was
proved end to end in the consumer before being relied on. Promoting the order to the contract is
the same fix PA-5 made for `matchesSearch()`: a host depending on an undocumented internal is a
host a patch release can break.

⚠️ **This paragraph used to end "that pattern is why PA-6 was not needed", and that was wrong.**
The aliasing pattern lets a host carry structured data ALONGSIDE the confirmation; it does not
let the host put any of it in the heading, because the heading was not a slot. What the consumer
actually shipped was a title, a body and an affected-counts line composed into the one string the
message slot accepted — and the live walk showed the result rendering under the package's generic
heading, with the actor's real question demoted to the body's first sentence. PA-6 was raised from
that screen (the consumer's adoption, T058); see below.

#### `authorizeTreeMove()` — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **security**

```php
protected function authorizeTreeMove(Model $node, ?TreeNode $newParent): bool;
```

**The hole.** `authorizeMove()` resolved ids through the host's `visibleQuery()` and did
nothing else, and **visibility is not permission**. The first real consumer calls
`authorize('update', $category)` on every committing path and pins an actor holding `view`
and **not** `update` being refused with nothing moved. Adopted as the package stood, **that
actor's move would have succeeded** (the consumer's adoption, research F3 — requested as PA-2).

⚠️ **This document asserted the opposite.** It previously read *"`placeNode()` re-checks the
host's **authorization** on the committing call"*. It re-checked the host's **visibility**.
A documented guarantee the implementation does not provide is worse than a missing one —
a host reading that sentence would reasonably not write a check of its own — so correcting
the wording is part of this amendment, not a tidy-up alongside it. (`AGENTS.md` R-037: a
quoted constraint is a claim.)

**Where it is asked, and why there.** Inside `authorizeMove()`, **after** the node and the
destination parent are resolved from the host's own scope and **before** any reference work:

- *after* resolution, so the host is handed real models rather than client-supplied ids —
  a slot given the raw payload would push the resolution problem back out to every host,
  and a host that answered `true` about an unresolved id would reopen the hole;
- *before* references, so an actor who may not move this node learns nothing about which
  neighbours exist.

Because `placeNode()` and `confirmPendingMove()` both route through `authorizeMove()`, the
host is asked on **every** committing path — including the confirming call, where an actor's
permissions may have changed since the move was queued.

**Two answers, both supported.**

| Host writes | Result |
|---|---|
| `return false` | Soft refusal: `tree.refused.unauthorized` is announced and shown, nothing is written, the page stays |
| `throw` (e.g. `$this->authorize('update', $node)`) | The exception is **not** caught — a 403. `commit()` catches `DomainException`, the refusal vocabulary, and `AuthorizationException` is deliberately not one |

**Default `true`.** The package has no idea what a host's permissions are, and inventing one
would be the package deciding (`AGENTS.md` R-003). ⚠️ A default of `true` fails *open*, which
is the wrong direction for a permission check — it is chosen anyway because the alternative
breaks every existing host into a tree that refuses everything, and because the package
genuinely cannot answer the question. **A host that has permissions must implement this
slot**; the package cannot detect that it has not.

**New copy**: `tree.refused.unauthorized`, `'You cannot move :name.'`. Unlike the other three
refusals it maps to **no exception type** — a host's `false` is an answer, not a refusal the
package raises. It names the node because the actor *can* see it, so the exists/not-exists
disclosure the `unreachable_reference` wording avoids does not arise.

**Migration**: none. Hosts that do not implement it keep their current behaviour.

#### `matchesSearch()` — ⚠️ AMENDMENT (during implementation, 001-tree-v1), documentation only

```php
protected function matchesSearch(Model $node, string $term): bool;
```

**No signature change, and no behaviour change.** The method was already `protected` and
therefore already overridable; the amendment is that it is now **in the member table**.

**Why that is not a no-op.** This document's own first line says anything not listed here *"is
internal and may change without a major version"*. So a host overriding `matchesSearch()` was
taking a private dependency on an internal — and the first real consumer **must** override it:
it searches name **or** `short_code`, and its own placeholder copy promises exactly that
(the consumer's adoption, research F6 — requested as **PA-5**). Leaving it undocumented meant a patch
release could silently break a host's search.

The package searches `TreeColumns::tiebreaker()`, which is the one column it knows a host
renders. A host showing a second identifier overrides this to search both.

⚠️ **An override must narrow, never widen.** Every node reaching this method already came out
of the host's own `visibleQuery()`, and the ancestor walk runs over that same set — so a
correct override compares attributes on `$node` and does not issue its own query. One that
queried afresh could surface a row privacy hides.

⚠️ **Its guard is a MUTATION red, not an absence red** — the slot worked before the amendment,
so nothing could fail for want of an implementation. `tests/Bridge/HostSearchSlotTest.php`
discriminates whether the search path still *asks* the host: bypassing `matchesSearch()` inside
`readSearchVisibleIds()` turns two of its six cases red while the rest of the suite stays green.

**Migration**: none.

#### `treeAccessibleName()` — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **accessibility**

```php
public function treeAccessibleName(): string;   // defaults to static::getNavigationLabel()
```

**What was wrong.** The view rendered

```blade
<div class="ltree-tree" role="tree" aria-label="{{ static::getNavigationLabel() }}">
```

so the tree's accessible name **was** the page's navigation label, and the only way to
change one was to change the other. The first real consumer has had two strings for the
two jobs since before this package existed — *"Vendor categories"* in the sidebar,
*"Vendor category hierarchy"* on the tree — and could keep both only by renaming its
navigation item (the consumer's adoption, T048 — requested as **PA-7**).

⚠️ **This sat inside the package's own accessibility remit and had neither the care nor
the slot the ROWS got.** `AGENTS.md` R-014 is about a row being announced by its own
name and R-013 about the ARIA properties sitting on the focusable element; the
*control's* own name was whatever the sidebar happened to say. A navigation label
answers "where am I going"; a tree's name answers "what is this control".

⚠️ **`@mixin` tightened from `BasePage` to `Filament\Pages\Page`** in the same change,
because that is where `getNavigationLabel()` is declared. Both host shapes descend from
it (`Filament\Resources\Pages\Page extends Filament\Pages\Page`). The requirement is not
new — the view always called that method — it was simply unanalysable inside a blade.

**Guards**: `tests/Bridge/AccessibleNameTest.php` (override, default fallback, and that
the navigation label does not move) and `tests/Browser/HostInitialStateTest.php`, which
asserts the name **through axe's own accname implementation** rather than by reading the
attribute back — the same discipline R-014 imposes on a row, for the same reason.

**Migration**: none. A host that overrides nothing keeps the name it has today.

#### `treeBranchesStartCollapsed()` — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

```php
public function treeBranchesStartCollapsed(): bool;   // defaults to false
```

**What was wrong.** The controller initialises `collapsed: {}` and `isExpanded()`
answered true for any key not explicitly closed, so **every branch of every host rendered
open** and no host could say otherwise. The first consumer's tree had started collapsed
since it was written; adopting the package silently flipped it (the consumer's adoption, T048 —
requested as **PA-8**).

⚠️ **Not a cosmetic default.** Arrows traverse DISPLAYED rows, so the initial state
decides what the keyboard visits, what a screen reader walks, and how many rows a large
tree paints at once.

⚠️ **Three states in the controller, not two.** `isExpanded()` now distinguishes a key
the actor opened, a key the actor closed, and a key **nobody has touched** — which is
where the host's answer applies. Reading `collapsed[key] !== true` collapsed the third
case into "open"; treating a missing key as closed would break reopening, which writes an
explicit `false`.

⚠️ **The SERVER-rendered markup carries the initial state too** — `aria-expanded` and a
`display: none` on the children container. Between the response and Alpine booting there
is no controller: `x-show` has done nothing and `aria-expanded` is whatever the markup
said. A page that renders "open" and lets the controller correct it announces the wrong
state to anything reading the document before then, and flashes every descendant of every
branch on first paint.

⚠️ **A closed branch's children stay IN the DOM** (`x-show`, never `x-if`). They are
members of their group whether or not the actor opened it, and if closing a branch removed
rows, the rendered set the client reports back would depend on what happened to be open —
the client-index defect constitution Principle II exists to prevent, arriving by another
door.

⚠️ **A boolean, not a list of keys.** Which branches an actor has opened is client state
the server has no opinion about; a host choosing per-node initial state would be a second,
server-side copy of that state, and the two would disagree the moment a chevron was
clicked.

**Guards**: `tests/Bridge/InitialCollapseTest.php` (served markup, both directions) and
`tests/Browser/HostInitialStateTest.php` (the controller's own answer after boot, the
arrow-key path, and reopening). Mutating `isExpanded()` back to two states turns 5 of the
browser cases red; the two that stay green are the default-host regression guard and the
axe pass, and both are meant to.

**Migration**: none. The default is `false`, which is what every host had.

#### The rendered structure — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **accessibility**

A `treeitem` is a **direct child** of the `tree` or of a `group`:

```html
<div role="tree">
  <div role="treeitem" data-ltree-key="1">…</div>
  <div role="group" data-ltree-children-of="1">
    <div role="treeitem" data-ltree-key="2">…</div>
  </div>
</div>
```

**What was wrong.** Every row and its children were wrapped in a `.ltree-branch` div,
so `role="tree"` owned generic divs and the rows were its GRANDchildren. ARIA names
`treeitem` and `group` as the tree's required owned elements; an unroled element in
between is neither (the consumer's adoption, T048 — **PA-10**).

⚠️ **axe does not report this, which is why it shipped.** The package's own
`assertNoAccessibilityIssues()` passed with the wrapper in place, because axe's
required-children check walks ANCESTORS rather than demanding a direct child. An
automated pass proved nothing here — `AGENTS.md` R-018's lesson about announcements,
arriving through the DOM instead. It was found by a consumer whose frozen selectors
were written `[role="tree"] > [role="treeitem"]` and simply stopped matching.

⚠️ **The group remains a SIBLING of its row, not a child of it.** A `treeitem` may own
a `group`, but nesting one inside the focusable row would put the whole subtree into
that row's accessible name — the R-014 defect this package was extracted to fix.

⚠️ `data-ltree-branch` is **gone**, and the drag's self-descendant check now asks the
dragged node's own `group` instead of climbing to a wrapper. Guarded by
`tests/Bridge/AriaStructureTest.php`, which WALKS THE DOM: a string search for the
class would have gone green the moment it was renamed while the structure stayed wrong.

**Migration**: a host that wrote CSS against `.ltree-branch` loses it. `.ltree-children`
now carries the flex column it provided.

#### A search reveals what it matched — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

**What was wrong.** The two halves of the search were built by different sides and
never met. The server narrows the rows and keeps a match's ancestors so it stays
reachable; the client decides which branches are open and knew nothing about the
search. So a match inside a closed branch was present in the DOM and **invisible** — a
search that found things and showed you none of them (the consumer's adoption, T048 — **PA-11**).

⚠️ **Not only a `treeBranchesStartCollapsed()` problem.** Any actor who had closed a
branch before typing got the same nothing, on any host, since the search shipped.
PA-8 made it the *first* experience rather than an occasional one, which is how it
was finally noticed.

`isExpanded()` now answers **four** states: a search is active → shown; the actor
closed it → closed; the actor opened it → open; untouched → the host's default.

⚠️ **It REVEALS rather than expands.** `collapsed` is not rewritten, so clearing the
search returns the tree to exactly the shape the actor had. Expanding for real would
leave a large tree fully open after one search.

⚠️ The controller reads `$wire.treeSearch`, which is reactive; a data attribute would
not re-run the bindings that read it, and the reveal would arrive a keystroke late or
not at all.

**Migration**: none.

#### What the keyboard refuses to pick up — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

Two refusals, both **courtesies**. The guards are unchanged and server-side:
`commit()` reports an only-child reorder and writes nothing, and `authorizeTreeMove()`
re-decides permission on every committing path.

**PA-12 — an only child is refused before the hold begins.** It could previously be
picked up and announced as *"picked up, 1 of 1"*, and heard `only_child` only when an
arrow key was pressed — an invitation to start a move that cannot exist. The refusal
counts the siblings the actor can SEE, like everything else here: a node whose only
sibling is hidden from this actor IS an only child to them, and behaving otherwise
discloses that the hidden row exists (R-015).

⚠️ `moveHeld()`'s `only_child` branch was **removed**, not left as a safety net: with
the pick-up refused it is unreachable, and dead code that reads like a guard is worse
than no guard. `only_child` now has two producers — `pickUp()` and `commit()`.

**PA-13 — a host slot for movability**, rendered as `data-ltree-immovable`:

```php
protected function canMoveNode(Model $node): bool;   // defaults to true
```

The only early refusal the package had was driven by `data-ltree-locked`, which comes
from `isValidTreeTarget()` — *"may this node RECEIVE children"*. Using it to answer
*"may this actor MOVE this node"* had two consequences, both wrong: a view-only actor
picked rows up freely and was refused at the far end of a round trip, and a node merely
closed to new children could not be reordered at all. The two attributes now answer the
two questions, and the package's own guard — which had been asserting the conflation —
was re-pointed to the new one.

⚠️ **A host implementing this and not `authorizeTreeMove()` has decorated its tree, not
protected it.** The markup is client-side and an actor can edit it.

**Migration**: a host relying on `data-ltree-locked` to prevent a pick-up must implement
`canMoveNode()`. Nothing else moves.

#### Focus survives the tree's own re-render — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

**What was wrong.** Every write goes through Livewire, so the rows are morphed after
every move. morphdom replaced the focused row, focus fell to `<body>`, and a keyboard
user was returned to the top of the document after each reorder — having to tab all the
way back in to make a second one. The roving tabindex still *said* a row owned the tab
stop; nothing held the DOM focus (the consumer's adoption, T048 — **PA-14**).

⚠️ **The morph hook existed and did nothing.** It checked which component had morphed
and then had no body at all. The check was written for a real defect — a host's
notification poll abandoning a held node — and the "do nothing on a foreign morph" half
is right; nothing was ever done on OUR morph either.

⚠️ **Focus is restored only if a ROW had it and lost it.** Two ways to write this wrong,
both worse than the defect: focusing on every morph yanks the actor out of a row action,
a modal or the search box; focusing `focusedId` unconditionally pulls focus INTO a tree
the actor never entered, which a host polling a notification bell would do every thirty
seconds.

⚠️ A bare `$wire.$refresh()` does **not** reproduce the defect — morphdom keeps an
untouched element — so the guard that matters is the one that performs a real keyboard
move and then asks where focus is.

**Migration**: none.

#### A held node moves on screen — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

**What was wrong.** The arrow keys announced a new position and **moved nothing**. A
screen-reader user heard *"position 1 of 3"*; anyone watching the screen saw the row
sit still until Enter. One keystroke told two audiences different things, and a sighted
keyboard user had no way to know the key had worked (the consumer's adoption, T048 — **PA-15**).

The held row's **block** — the row, its leaf slot and its children group — is moved
among its siblings on each arrow press, and put back exactly on cancel or abandon.

⚠️ **Still nothing is written.** The hold reaches the server once, at the put-down.
`heldSiblings` remains the group as it was at pick-up, which is what makes the preview
repeatable and its undo exact: moving only the held block never reorders the others.

⚠️ **A block, not an element** — since PA-10 a row's children group is its SIBLING.
Moving the row alone would tear a subtree away from its parent on screen while the
server still believed the old shape.

⚠️ **Moving a focused element blurs it, and the blur is not the actor leaving.** The
first working preview abandoned its own hold: the move fired `focusout`,
`onTreeFocusOut()` read that as leaving the tree, and `moveHeld()` then announced over
an emptied state — literally *", position 1 of 0."*. The move now marks its own blur
and re-focuses the row, because the actor still has to BE on it to press the next key.

⚠️ **The rendered order is no longer proof that the server committed.** Two of this
package's own guards used it as their wait signal and started reading the database
before the write; they now wait on the stored rows. Any host test that waits on
rendered order after a keyboard move must do the same.

**Migration**: none, unless a host's tests waited on rendered order as a commit signal.

#### `Tab` abandons a hold — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

**What was wrong.** `Tab` left a node held. The tree is documented as one tab stop, but
a host's row actions are real focusable buttons **inside** it — so Tab moved focus from
the row to its own edit button, `onTreeFocusOut()` saw focus still inside
`[role="tree"]`, and the node stayed held while the actor had visibly left it
(the consumer's adoption, T048 — **PA-16**).

⚠️ **`KeyboardTraversalTest`'s one-tab-stop claim counts ROWS** with `tabindex="0"`, so
it cannot see this. The claim is about the tree's rows, not about everything focusable
in them; a tree whose rows carry action buttons has more tab stops than the pattern
implies, and that is a property of hosting actions in rows rather than a defect to fix
by making a host's buttons unreachable.

⚠️ **The keystroke is NOT consumed.** Tab keeps moving focus; swallowing it would trap
a keyboard user inside the tree, which is worse than the defect being fixed.

**Migration**: none.

#### Typing a host's slots — ⚠️ AMENDMENT (during implementation, 001-tree-v1), documentation only

The slots take `Model $node`, so `$node->name` is an undefined property to a host running
static analysis at a useful level. **A host types its own slots**, and the supported way
is a PHPDoc `@param`:

```php
/** @param  VendorCategory  $node */
protected function badgesFor(Model $node): array
{
    return [$node->short_code];      // analysed as VendorCategory
}
```

⚠️ **Verified on both host shapes, because they do NOT behave alike.**

| A host that… | may narrow the NATIVE type | may narrow with `@param` |
|---|:---:|:---:|
| uses `InteractsWithTree` directly | ✅ — a using class's method **shadows** the trait's, and PHP runs no compatibility check | ✅ |
| extends `TreePage` | ❌ — the slot is inherited from a **class**, where PHP enforces LSP: `Declaration of … must be compatible with …` | ✅ |

So native narrowing works on one shape and is a **fatal error** on the other, while
`@param` works on both. That asymmetry is why this is documented rather than left to each
consumer: the first one concluded from the base-page rule that narrowing was impossible
*anywhere*, and wrote a throwing `category(Model $node): VendorCategory` helper called
from six slots (the consumer's adoption — requested as **PA-9**).

⚠️ **A generic trait was tried for this and REJECTED on evidence.** `@template TNode of
Model&TreeNode` types a base-page host's slots automatically — but it cannot be made sound
inside the package, and it does not help a trait host at all:

- larastan resolves `Builder<TNode>::get()` to the template's **bound**, never to `TNode`
  (with an intersection bound it splits further, into
  `Collection<int, Model>|Collection<int, TreeNode>` — a union in which neither member has
  both APIs). So no internal read can be claimed as `TNode`, and every internal call into a
  `TNode` slot becomes an `argument.type` error. Eight of them, clearable only with casts or
  inline `@var` — which `AGENTS.md` R-031 forbids;
- PHPDoc inheritance does not reach a method that **shadows** a trait method, so
  `@use InteractsWithTree<Category>` types nothing on the trait path — the shape the only
  consumer uses.

Recorded rather than shipped, and rechecked if larastan's builder generics improve.

**Migration**: none. Nothing about the code changed.

#### Which event a placement fires — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

`commit()` compares the destination parent with the node's **stored** parent and routes:

| Placement | Action | Event |
|---|---|---|
| destination parent **differs** — a re-parent | `PlaceNode` → `MoveNode` | `NodeMoved` |
| destination parent is **the same** — a reorder | `ResolveSiblingPlacement` → `ReorderSiblings` | `SiblingsReordered` |

**Why.** `commit()` previously went `PlaceNode` → `MoveNode` → `NodeMoved` for *every*
placement, and `grep -rn 'ReorderSiblings' src/ resources/` found no caller outside the
action's own file. Two consequences, both real (the consumer's adoption, research F4 — **PA-3**):

1. **`SiblingsReordered` had no producer.** The package shipped, and this document
   published, an event nothing in the bridge could ever fire.
2. **A host's audit trail lost the reorder.** A same-parent drag or keyboard reorder
   recorded `moved` instead of `reordered`, discarding the written order and the "performed
   on the parent, on nothing at the root" shape the source application has always had.

⚠️ **The index still comes from `ResolveSiblingPlacement`, against the complete group.** The
reorder path names a neighbour and asks the package where that falls; it does not count rows.
The ordered keys handed to `ReorderSiblings` come from `SiblingGroup`, the single copy of the
read order — so they cannot drift from the list the index was resolved against. This is
constitution Principle II, and a reorder path that recomputed the order itself would have
been a second copy of exactly the mapping that caused the defect.

⚠️ **The root case is why `ReorderSiblings` took `$model`.** Root keys carry no model class
and there is no parent to infer one from, so the bridge names `$node::class`. The amendment
above that added the parameter and this one that finally calls it are the same story.

⚠️ **PA-3 changes which action runs, NOT which moves are permitted.** `MoveNode` refuses a
destination whose `isValidTreeTarget()` is false, so before this change a reorder inside an
inactive parent was refused; `ReorderSiblings` asks nobody. The reorder path therefore asks
the same question before writing. This is a **deliberate second copy** of that guard —
`MoveNode` keeps its own for direct callers — and it is load-bearing rather than redundant:
without it, adopting PA-3 would silently start *allowing* reorders inside a frozen parent, a
widening no consumer requested. Do not tidy either copy away on the evidence that the suite
stays green without it (see `checklists/validation-log.md` on guards held by two mechanisms).

**Migration**: a host listening only for `NodeMoved` **stops hearing same-parent reorders**
and must also listen for `SiblingsReordered`. That is the point of the amendment, and it is
the one behavioural break in PA-1…PA-4.

#### The announcement placeholder contract — ⚠️ AMENDMENT (during implementation, 001-tree-v1)

A host overriding `tree::tree.announce.*` may use these placeholders, **per key**. Anything
else is emitted literally.

| Key | `:name` | `:position` | `:total` | `:parent` | Raised by |
|---|:---:|:---:|:---:|:---:|---|
| `picked_up` | ✅ | ✅ | ✅ | — | controller |
| `moved` | ✅ | ✅ | ✅ | — | controller |
| `put_down` | ✅ | ✅ | ✅ | — | controller |
| `cancelled` | ✅ | ✅ | ✅ | — | controller |
| `abandoned` | ✅ | ✅ | ✅ | — | controller |
| `already_first` | ✅ | ✅ | ✅ | — | controller |
| `already_last` | ✅ | ✅ | ✅ | — | controller |
| `only_child` | ✅ | ✅ | ✅ | — | controller **and** `commit()` |
| `refused` | ✅ | — | — | — | controller |
| `moved_into` | ✅ | ✅ | ✅ | ✅ | ⚠️ **nothing — see below** |

**What was wrong.** Four keys — `picked_up`, `cancelled`, `already_first`, `already_last` —
were handed only `:name`. A host whose wording used `:position` or `:total` had the literal
text `":position"` announced to a screen-reader user (the consumer's adoption, research F10 — **PA-4**).
It rendered fine and passed every assertion, which is the `:daysd` failure mode 072 already
paid for once.

⚠️ **The package's own sweep cannot catch this, structurally.**
`tests/Core/HostOverridesCopyTest.php` checks the package's lang file against the package's
token list — and the package's own wording does not use the missing tokens. The mismatch
exists **only once a host overrides**, so the guard has to be a host override. That is
`tests/Fixtures/Panel/AnnouncementTreePage.php`, exercised through a real browser.

**One rule, not four fixes.** While a node is held the controller knows the name, the position
and the group size, so it now passes all three to every announcement raised in that state.
Replacements a template does not use are ignored, so this is **additive** for every existing
host. `refused` is raised on a locked row that was never picked up and has no such context.

⚠️ **`cancelled` and `abandoned` announce the ORIGINAL position**, not where the abandoned
move had walked the node to. The node returns to where it started; announcing anything else
tells a non-sighted user the node is somewhere it is not.

⚠️ **`only_child` is raised from two producers** — the controller and `commit()` — and both
now pass the same three tokens. A key that substituted `:total` in the browser but not on the
server would be this same defect, reachable through whichever producer a host did not
exercise.

⚠️ **`moved_into` has no producer.** The template ships and is documented, but
`grep -oE "say\('[a-z_]+'" resources/js/tree.js` lists nine keys and this is not one of them —
the controller never announces a re-parent as "inside :parent". This is the same shape as the
`SiblingsReordered` gap PA-3 closed, found the same way, and it is **not fixed here**: PA-4's
scope is the placeholder contract, and the consumer's F10 records `moved_into` as *"a tenth
announcement this app has never had"* — an addition to design with the host that wants it,
not a hole to fill on a guess. Recorded rather than quietly dropped.

### ~~`Rolland\Tree\Filament\Testing\AssertsTree`~~ — **RETRACTED, never shipped**

⚠️ **AMENDMENT (during implementation, 001-tree-v1).** This entry promised assertion helpers
hosts could use in their own suites. **They were never built**, no task in `T001`–`T100` covered
them, and `/speckit-analyze` found the gap: a documented public API that does not exist.

**Retracted rather than written**, and the reason is this document's own logic. The entry itself
said the helpers were *"explicitly **not** required — a host may assert against its own markup
instead."* Building them now would mean designing public surface with **zero consumers**,
immediately before the adoption slice that would show what a host actually needs. That is the
mistake `AGENTS.md` R-035 names for tags — *"an API frozen without a consumer is frozen against
guesses"* — and it applies to surface area just as it does to versions.

**Migration**: none. Nothing was ever published under this name, so nothing can depend on it.

**If it comes back**, it should arrive from the consumer's adoption asking for it, with the
assertions that adoption actually wanted. ⚠️ Two things that must be settled then, and were
already visible when a draft was sketched: it uses no Filament, so `src/Filament/Testing/` is
probably the wrong home; and it needs `phpunit/phpunit`, which is a dev dependency, so it must be
scoped to tests or declared in `suggest`.

---

## Blade and JavaScript surface

⚠️ Package blades and the compiled assets are **not** a public API. Hosts extend the page class
and use the slots above; they do not `@include` package views or call into the Alpine controller.
The one exception, which **is** public: the CSS class prefix `ltree-` is stable, so a host may
write its own overrides against it.

---

## Explicitly NOT in the public surface

Merge, bulk-assign, edit, retire/restore, record listings, unfiled-record panels, nested-set or
materialized-path storage, cross-tree drag, multi-select moves, and undo. See spec § Out of Scope.

#### `confirmationFor()` may name its own question — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **PA-6**

```php
protected function confirmationFor(Model $node, ?TreeNode $newParent): string|array|null;
```

**What was wrong.** The slot accepted one string, and that string was the BODY. The heading was
always `tree::tree.confirm.heading` — *"Confirm this move"* — so a host with a real question to
ask had nowhere to ask it. The first consumer composed its title, its body and its
affected-counts line into the single string, and the rendered confirmation carried two headings:
the package's generic one in the heading slot, and *"Make this branch private?"* buried as the
first sentence of the paragraph. The generic one won the visual hierarchy (the consumer's adoption, T058).

A host may now return either shape:

| returned | heading | body |
|---|---|---|
| `null` | — | the move applies immediately, unchanged |
| `'…'` | `tree::tree.confirm.heading` | the string, unchanged |
| `['heading' => …, 'message' => …]` | the host's | the host's |

⚠️ **The string form is not deprecated and its rendering is byte-identical.** It is the whole
installed base; a guard pins it (`ConfirmHeadingTest`), and the array form is normalised into the
same `$pendingMove` shape at the one point that already built it.

⚠️ **`heading` is stripped before the payload is committed**, exactly as `message` already was.
Both describe the question, not the move, and the resolver must never see either.

**Migration**: none. An override that still declares `?string` is a narrower return type than the
parent's `string|array|null`, which PHP allows.

#### The confirmation takes focus — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **accessibility, F39**

**What was wrong.** The confirmation was already `role="alertdialog" aria-modal="true"` with a
correct `aria-labelledby`, and nothing ever moved focus into it. Measured in a real consumer
panel: the confirmation appeared and `document.activeElement` was still the drag handle of the
row just dragged, while the live region held the PREVIOUS announcement. A keyboard actor tabbed
forward blind to reach the answer; a screen-reader actor was told only whatever their AT
volunteers for an alertdialog that never received focus.

The region now carries `tabindex="-1"` and takes focus when it appears, and focus returns to the
row the move concerned once the confirmation is answered.

⚠️ **The REGION takes focus, not the submit button.** The heading is what has to be read, and
pre-focusing *"Move it"* would put the destructive answer under the actor's next Enter.

⚠️ **An answered confirmation is detected by its DISAPPEARANCE, not by focus and not by a click.**
Two earlier attempts were wrong in ways worth recording, because both look correct:

- *Reading `document.activeElement` in the pre-morph hook.* Livewire **disables the button it is
  submitting**, and a disabled element cannot hold focus — so focus has already fallen to
  `<body>` before any morph hook runs, and the dialog looks like it was never involved.
- *Recording it on the buttons' own click.* That works only once Alpine has bound the handler. A
  click landing before that silently skips it — a human cannot click that fast and a test can,
  which is the kind of race that reads as a flake for weeks.

The hook now records the dialog's key from its PRESENCE before the morph, and restores focus only
if the dialog is gone afterwards — so a morph that leaves the confirmation standing (a host's
poll, a notification) cannot yank focus out of it.

**Migration**: none.

#### Dark mode follows the panel's class — ⚠️ AMENDMENT (during implementation, 001-tree-v1), **PA-17**

**What was wrong.** `resources/css/tree.css` carried its dark styling behind
`@media (prefers-color-scheme: dark)`. Filament does not paint from the OS preference: its theme
script resolves the actor's choice — or the OS, when the actor has expressed none — and stamps
`<html class="dark">`. The two agree only while nobody has used the theme switcher.

Measured in a real consumer panel, OS light and the dark theme chosen: `.ltree-row` kept its
light rule's `background-color: rgb(255, 255, 255)` while inheriting Filament's dark
`color: rgb(255, 255, 255)` — **contrast 1:1, with the row's name not rendered at all** (finding
F38). The inverse, a dark machine with the light theme, painted dark rows onto a light page.

The dark rules are now `.dark`-scoped and the media query is gone.

⚠️ **Why the existing dark guards could not fail.** `inDarkMode()` emulates the OS preference, and
Filament's own script then follows it — so the harness set the media query and the class together
and the two mechanisms could never disagree. The guards that replace them drive the class
directly, and one of them asserts the HARM (`background-color` ≠ `color`) rather than a palette
value, so a future palette change cannot reintroduce the collision silently.

**Migration**: a host that deliberately relied on the tree following the OS while its panel did
not — no known host does — loses that. Everything else moves from broken to correct.

#### `treeReorderEnabled()` — ⚠️ AMENDMENT (after v0.9.0, 001-tree-v1), **PA-18**

```php
public function treeReorderEnabled(): bool;   // defaults to true
```

**What was missing.** The package could refuse a move **per node** and **per actor** —
`canMoveNode()` for presentation, `authorizeTreeMove()` at the commit. Both are *permission*
answers. Neither can say **"this page has no concept of order"**, and the second consumer's
Drive-style folder browser is exactly that page: the tree is a navigation control, and a folder's
position among its siblings means nothing there.

⚠️ **Why `canMoveNode()` returning false everywhere is not the same thing, and is worse than
nothing.** A false answer sets `data-ltree-immovable` and refuses a pick-up — but the drag handle
**still renders on every row**, and the keyboard **still answers Space**, with a *permission*
refusal. That page would therefore show an affordance it cannot honour, and announce *"you cannot
move this"* about a concept it has retired. The sighted user sees a handle that does nothing; the
screen-reader user is told they lack a permission, which is a different and false statement. It is
a lie in the only channel that user has, and it is the same visual/semantic disagreement that
produced PA-10 and finding F38. Verified by reading the package rather than assumed: the handle is
unconditional in `tree-branch.blade.php`, the keyboard stays bound in `tree.js`, and a `grep` for
`reorderEnabled|treeReorderable|showHandle|handleVisible` matched nothing.

**What a `false` answer does — and only this:**

| | Behaviour |
|---|---|
| C1 | No `.ltree-handle` is rendered on any row |
| C2 | Nothing carries `draggable="true"` |
| C3 | The Space binding is not registered — the keystroke is not even consumed |
| C4 | The live region stays **empty**; nothing about ordering is announced |

**What a `false` answer must NOT do — and this is the whole point of calling it an amendment:**

| | Unchanged |
|---|---|
| C5 | `role="tree"` / `treeitem` / `group` and every `aria-*` |
| C6 | Expand and collapse, by pointer and by ArrowRight/ArrowLeft |
| C7 | Search-reveal (PA-11) |
| C8 | `data-ltree-locked` and `data-ltree-immovable` keep their PA-13 meanings on the default path |

⚠️ **The default cannot be false.** Every existing host already has ordering, and flipping the
default would silently remove it from all of them. The regression guards assert the default path
as loudly as the new one, because a mutation run proved the **bridge suite alone could not have
caught a false default** — it never asserted the handle's presence. Nineteen *browser* guards do.

⚠️ **Two mechanisms, and each is separately necessary.** The blade omits the handle; the
controller omits the Space binding. That looks like the two-mechanism shape findings F1, F9 and
F10 warn about — correct code no single mutation can show is needed — and it was checked rather
than assumed. Removing only the controller guard reddens C3/C4 and leaves C1/C2 green; removing
only the blade guard reddens C1/C2 and leaves C3/C4 green. They cover *different* surfaces, not
the same one twice, so neither may be "tidied away".

⚠️ **Presentation, not permission.** This slot does **not** refuse `placeNode()`. The security
boundary is unchanged and is still `authorizeTreeMove()`, re-decided on every committing call
(PA-2) — a slot that removes an affordance was never the guard, exactly as PA-13 is not.
Deliberate, and not an oversight: making a `false` here refuse the committing path would also
foreclose **drag-to-nest**, which is a *move* rather than a *reorder* and which the same consumer
needs next. The contract puts removing the ordering code explicitly out of scope for the same
reason.

**Migration**: none. A host that overrides nothing keeps the ordering it has today.


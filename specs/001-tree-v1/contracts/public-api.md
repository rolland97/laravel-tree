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
| `confirmationFor(Model $node, ?TreeNode $newParent): ?string` | no | Return a warning to require confirmation; `null` applies immediately |
| `authorizeTreeMove(Model $node, ?TreeNode $newParent): bool` | no | ⚠️ The host's **permission** rule. Defaults to `true` |
| `matchesSearch(Model $node, string $term): bool` | no | Which columns a quick search looks in. Defaults to the tie-breaker |
| `treeStrings(): array` | no | Override the announcement templates |

Public Livewire entry points on the page: `placeNode(...)`, `confirmPendingMove()`,
`cancelPendingMove()`.

⚠️ **`placeNode()` re-runs the host's `visibleQuery()` on the committing call**, and — since
PA-2 — asks `authorizeTreeMove()` there too, regardless of any check made for presentation.
The keyboard refuses a pick-up early as a *courtesy*; that refusal is not the guard.

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

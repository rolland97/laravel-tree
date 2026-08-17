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

### `Rolland\Tree\Filament\Pages\TreePage`

Abstract. A host extends it and implements or overrides:

| Member | Required? | Purpose |
|---|---|---|
| `static string $model` | **yes** | The tree model |
| `visibleQuery(): Builder` | **yes** | ⚠️ The host's privacy scope. The package never adds one |
| `badgesFor(Model $node): array` | no | Row badges |
| `rowActions(Model $node): array` | no | Row actions |
| `headerActions(): array` | no | Page-level actions |
| `leafSlot(Model $node): ?View` | no | Non-node rows beneath a node |
| `confirmationFor(Model $node, ?TreeNode $newParent): ?string` | no | Return a warning to require confirmation; `null` applies immediately |
| `treeStrings(): array` | no | Override the announcement templates |

Public Livewire entry points on the page: `placeNode(...)`, `confirmPendingMove()`,
`cancelPendingMove()`.

⚠️ **`placeNode()` re-checks the host's authorization on the committing call**, regardless of
any check made for presentation. The keyboard refuses a pick-up early as a *courtesy*; that
refusal is not the guard.

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

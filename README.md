# laravel-tree

Adjacency-list tree management for Eloquent, plus an accessible drag-and-keyboard
[Filament](https://filamentphp.com) v5 tree page.

The core is plain Laravel and does not require Filament. The Filament bridge is optional.

---

## The one rule this package exists to enforce

**A move names a neighbour, never an index.**

```php
app(PlaceNode::class)->handle(
    node: $category,
    destinationParent: $parent,
    reference: $neighbour,
    placement: SiblingPlacement::After,
    renderedSiblingIds: $idsTheActorCouldSee,
);
```

No public entry point accepts a caller-supplied numeric index — not as an overload, not
deprecated, not behind a flag.

The reason is not style. **The browser cannot count rows it never drew.** Privacy scoping
removes rows from the query and quick-search removes them from the DOM, so an index counted
client-side resolves server-side against a different list. In the application this package was
extracted from, that silently renumbered a partial sibling list and left the rest holding stale,
colliding positions — one person's reorder shuffled the tree for a different person, and no test
noticed because the read order tie-breaks by name. It is also an authorization hole: an index
lets a tampered payload address a node that privacy hides.

So `ResolveSiblingPlacement` resolves against the **complete** destination group, while you pass
the siblings the actor could actually **see** as a separate argument. A reference that is not in
the complete group, or that the actor was never shown, is refused rather than resolved to a best
guess.

⚠️ `$renderedSiblingIds` has **no default value**, on purpose. An empty default would silently
turn that refusal into a no-op. If you genuinely render everything, pass everything.

---

## Requirements

| | |
|---|---|
| PHP | 8.3+ |
| Laravel | 13 |
| Filament | 5.x — **optional**, bridge only |

## Install

```bash
composer require rolland97/laravel-tree
php artisan vendor:publish --tag=tree-migrations   # publishes a STUB — edit the table name
php artisan migrate
```

⚠️ The schema change ships as a `.stub`, not as an auto-discovered migration. The package cannot
know your table name, your key type, or whether you want the foreign key constrained, so you
publish it, name your table, and run it yourself.

Then add the trait and answer the one question the package cannot answer for you:

```php
use Rolland\Tree\Concerns\IsTreeNode;
use Rolland\Tree\Contracts\TreeNode;

class Category extends Model implements TreeNode
{
    use IsTreeNode;

    public function isValidTreeTarget(): bool
    {
        return $this->is_active;   // YOUR rule. The package has no opinion.
    }
}
```

## Configuration

Publish it only if you need to change something:

```bash
php artisan vendor:publish --tag=tree-config
```

| Key | Default | Purpose |
|---|---|---|
| `parent_column` | `parent_id` | Your nullable self-referencing key |
| `position_column` | `position` | Order among siblings sharing a parent |
| `tiebreaker` | `name` | Second sort key — see below |

A group is read as **position ascending, then the tiebreaker ascending**. The tiebreaker is not
cosmetic: real data violates the contiguity guarantee (legacy rows, rows written before you
adopted this package, rows in groups no move has touched). Without it, two nodes sharing a
position order arbitrarily by driver, and the resolved index means something different from what
the user saw.

Configuration is global to the package in v1, not per-model.

## Refusals

Each is a distinct type, because you react differently to each:

| Exception | Raised when |
|---|---|
| `CycleException` | the destination is the node itself or one of its descendants |
| `InvalidTargetException` | you answered `false` to `isValidTreeTarget()` |
| `UnreachableReferenceException` | the reference is not in the complete group, was not among the rendered siblings, or is the node being moved |

⚠️ The three `UnreachableReference` causes share one type deliberately. They are one fact from
the caller's side — *you named something you could not have aimed at* — and separating them would
let a caller distinguish "this exists but you cannot see it" from "this does not exist".

## Events, not an audit log

The package writes no audit record and depends on no activity log. It fires
`NodeMoved` and `SiblingsReordered`; you listen if you want a trail. You already know who your
actor is; the package would have to guess.

A completed interaction fires **exactly one** move event, however many keystrokes or pointer
events produced it.

## The Filament bridge

```php
class TreeCategories extends \Rolland\Tree\Filament\Pages\TreePage
{
    protected static string $model = Category::class;

    protected function visibleQuery(): Builder
    {
        return Category::query()->visibleTo(auth()->user());   // YOUR privacy scope
    }
}
```

Optional hooks: `badgesFor()`, `rowActions()`, `headerActions()`, `leafSlot()`,
`confirmationFor()`, `treeStrings()`.

### Assets

**No bundler, no npm, no theme change, and no `@source` glob pointed into `vendor/`.** The
package's CSS and JS are compiled and committed here, and registered through Filament's asset
manager.

⚠️ The honest form of that claim: `php artisan filament:assets` copies registered assets into
`/public`, so a command *does* run — but Filament's own installer wires `filament:upgrade` into
`post-autoload-dump`, so in a normal Filament application it already fires on every
`composer install`. There is no step your app does not already run. There is no step of *ours*.

The CSS class prefix `ltree-` is stable and public, so you may write your own overrides against
it. Package blades and the compiled JavaScript are **not** a public API.

## Accessibility

The tree is one tab stop (roving tabindex). Arrows traverse displayed rows without wrapping;
Right expands then descends, Left collapses then ascends, Home/End jump to the ends. Nodes can
be picked up, moved, put down and cancelled from the keyboard, with every transition announced.

`aria-posinset` and `aria-setsize` count the **rendered** siblings, never the true group size —
that is a privacy requirement before it is a convention, because the true size discloses that a
node exists which the actor may not see.

⚠️ **What is proved, and what is not.** Automated accessibility checks run over the rendered tree
in both colour schemes and report zero violations, and each row's announced name is asserted
directly against an accname implementation — its own name only, excluding its badges, its action
labels and its subtree.

**None of that proves the announcements work as heard sentences.** Axe proves a name *exists*; it
cannot see a row that announces its entire subtree, because that is a name. The keyboard
announcements have **not** been walked with a real screen reader by someone who can hear them, so
that criterion is reported **unproved** rather than inferred from the automated checks. The
source application shipped this exact tree with four *correct* ARIA assertions passing while the
announced name was wrong.

## What is not here

Merge, bulk-assign, edit, retire/restore, record listings, unfiled-record panels, nested-set or
materialized-path storage, cross-tree drag, multi-select moves, and undo. Contribute those
through the row and header slots — they are your product, not this package's.

## Testing

```bash
composer test        # all three suites
composer analyse     # phpstan
composer test:lint   # pint
```

## License

MIT.

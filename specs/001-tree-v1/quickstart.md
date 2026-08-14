# Quickstart — laravel-tree v1

Install, adopt, and — the part that matters — **how each success criterion is actually proved**.

---

## Install (core only)

```bash
composer require rolland97/laravel-tree
php artisan vendor:publish --tag=tree-migrations   # publishes the stub; edit the table name
php artisan migrate
```

Add the trait and answer the one question the package cannot:

```php
use Rolland\Tree\Concerns\IsTreeNode;
use Rolland\Tree\Contracts\TreeNode;

class Category extends Model implements TreeNode
{
    use IsTreeNode;

    public function isValidTreeTarget(): bool
    {
        return $this->is_active;
    }
}
```

Move something:

```php
app(PlaceNode::class)->handle(
    node: $category,
    destinationParent: $parent,
    reference: $neighbour,
    placement: SiblingPlacement::After,
    renderedSiblingIds: $idsTheActorCouldSee,   // ⚠️ not optional — see contracts/public-api.md
);
```

## Install (Filament bridge)

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

No theme change, no `@source` glob, no npm. `php artisan filament:assets` publishes the
package's compiled CSS and JS — and a normal Filament application already runs it on every
`composer install` via the `filament:upgrade` hook, so usually nothing extra is needed.

---

## Verification procedures

⚠️ **Read this section before claiming any criterion is met.** Several of these exist because
the equivalent claim was made wrongly once already.

### SC-002 / SC-003 — ordering is correct and storage is clean

Assert against **stored rows**, never the rendered list:

```php
expect(Category::where('parent_id', $parent->id)->orderBy('position')->pluck('id')->all())
    ->toBe([$a->id, $d->id, $b->id, $c->id]);

// and separately — a display assertion cannot see this:
expect(Category::where('parent_id', $parent->id)->pluck('position')->all())
    ->toBe([0, 1, 2, 3]);
```

⚠️ **These must be two separate test cases, not one `->and()` chain.** A chain stops at the
first failure, so the contiguity assertion appended to the ordering one could never be watched
failing on its own — a lesson from the source application, applied rather than re-learned.

### SC-002 — the partial-visibility case

The one that matters. Build a group where a hidden sibling sits **between** two visible ones,
then request a move naming only what was visible:

```php
// visible: Alpha, Charlie      hidden between them: Aardvark
$page->call('placeNode', /* ... after Alpha ... */);

// the moved node lands between Alpha and Aardvark — NOT between Alpha and Charlie
```

⚠️ **Name the hidden sibling so the right and wrong answers differ.** Because the read
tie-breaks by name, a fixture called `Zulu` would produce the same visible order under both a
correct and a broken implementation, and the test would pass either way. `Aardvark` is the name
the source application settled on for this reason.

### SC-004 — every refusal is real

For each of the five refusal paths, watch the guard fail before it exists. A guard that has
never been red is unverified (`AGENTS.md` R-023).

⚠️ **If a guard cannot be made to fail, that is the finding** — investigate rather than accept
it. Four such probes in the source application each exposed something real: a redundant guard,
an untested path, a fixture whose "unbreakable" string was full of break opportunities, and a
layout rule the guard could not see because an ancestor clipped the overflow.

### SC-005 — styling in a themeless panel

Not provable in the test suite: **the harness serves no compiled CSS**, so every assertion about
appearance passes vacuously. Render the page in a real browser, in a panel with no custom theme,
and check **light and dark**.

⚠️ Two false conclusions were reached this way in the source application, both from trusting the
page instead of the stylesheet. A focus ring appeared not to compile — the dev server was
serving no CSS at all, because a stale hot-file named a Vite server that was not running. Then
the probe that "proved" a utility had compiled built a bare `<div>` and read `outline-style`,
**whose default is already `none`**, so it could not have failed.

### SC-006 — keyboard operability

Complete a full reorder with **zero** mouse input.

⚠️ Two harness traps (research R11): `keys($selector, ...)` is focus-then-type, so a key pressed
mid-expand lands on the *previously* focused row and reads as a product bug — follow every expand
with a waiting assertion. And `$wire.$refresh()` returns a promise that never resolves when
awaited in a page evaluation; void it, or the run hangs.

### SC-007 — automated accessibility

Run axe over the rendered page in **both** schemes, expecting zero criticals.

⚠️ **This proves less than it looks.** Axe checks that a name *exists*, not that it is sensible.
It cannot see a row announcing its entire subtree, because that is a name.

### SC-008 — row naming and set counts

Dump the accessibility tree and confirm each row reports **its own name only**, with position and
set size counting the **rendered** siblings.

⚠️ **This is evidence for SC-008, and it is NOT evidence for SC-011.** A dump proves the data a
screen reader receives. It says nothing about whether the announcements make sense as heard
sentences in sequence.

### SC-011 — the screen-reader walk ⚠️ CANNOT BE AUTOMATED

**Required**: a machine with a real screen reader (NVDA is free) and **working audio**, driven by
a person who can hear the output.

Walk, and record what was *heard*, not what was in the DOM:

1. Tab to the tree. Is it announced as a tree, and is it one tab stop?
2. Arrow through siblings. Is each row's own name announced, and nothing else?
3. Arrow into and out of a branch. Are expansion and level announced?
4. Reach a group where rows are hidden from you. Does the set size describe only what you see?
5. Pick a node up. Is the pick-up announced?
6. Move it. Is each new position announced, and is it comprehensible without seeing the tree?
7. Put it down. Is completion announced?
8. Repeat and cancel instead. Is the cancellation announced, and is the tree unchanged?
9. Repeat and Tab away mid-hold. Is the abandonment announced?
10. Try each boundary — only child, already first, already last — and a node you may not move.

**Until this is walked, SC-011 is reported UNPROVED.** Do not infer it from SC-007 or SC-008,
and do not record a tree dump in its place. The source application shipped this exact tree with
four *correct* ARIA assertions passing while the announced name was wrong, and it currently has
two such walks outstanding for want of a machine with audio.

### SC-010 — the core stands alone

```bash
composer remove --dev filament/filament
vendor/bin/pest --testsuite=core
```

An architecture test over namespaces is **not** a substitute (research R8): it proves nothing was
imported, not that the package boots.

---

## Release gate

⚠️ **Do not tag until the consumer application has adopted this package through a local path
repository and its suite is green — including its existing audit tests, unchanged.** A published
version cannot be retracted, and an API frozen without a consumer is frozen against guesses. The
repository stays private until then (`AGENTS.md` R-035, R-036).

Before the first tag, confirm the distribution archive carries runtime only:

```bash
git archive --format=tar HEAD | tar -t | grep -E '^(specs|tests|\.specify|\.claude|resources/(css|js)/)' \
  && echo "LEAK — add export-ignore" || echo "clean"
```

<?php

declare(strict_types=1);

namespace Rolland\Tree\Filament\Concerns;

use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Actions\ResolveSiblingPlacement;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Support\SiblingGroup;
use Rolland\Tree\Support\TreeColumns;

/**
 * An accessible drag-and-keyboard tree, as a trait any Filament page can use.
 *
 * ⚠️ The host supplies `visibleQuery()`. THE PACKAGE NEVER ADDS A PRIVACY SCOPE —
 * it owns the ordering rule, the host owns who may see what (constitution
 * Principle III, AGENTS.md R-003).
 *
 * ⚠️ **Why a trait and not only a base page.** `route()` is declared solely on
 * `Filament\Resources\Pages\Page`, so a tree registered as a resource's index page
 * must extend that class — and PHP has no second inheritance slot to also reach a
 * package base page. `TreePage` is now a three-line class over this trait, so both
 * hosts run the same body and neither can drift.
 *
 * ⚠️ **`$model` is deliberately NOT here.** A trait property and a using class's
 * own property with different initial values is a fatal composition error, so a
 * trait declaring `protected static string $model` would refuse to compose with
 * every host that names its model — which is all of them. The property and
 * `treeModel()` stay on `TreePage`; nothing in this trait needs them.
 *
 * @property-read string $treeSearch
 *
 * @mixin BasePage
 */
trait InteractsWithTree
{
    public string $treeSearch = '';

    /**
     * A move the host asked to confirm. The RAW payload is kept, never a resolved
     * one — see confirmPendingMove().
     *
     * @var array{nodeKey: int|string, destinationParentKey: int|string|null, referenceKey: int|string|null, placement: string, renderedSiblingIds: array<int, int|string>, message: string}|null
     */
    public ?array $pendingMove = null;

    /**
     * Per-request caches for the two reads the render repeats.
     *
     * ⚠️ `protected`, so Livewire neither serialises them into the payload nor
     * carries them across requests — the lifetime that is correct here is exactly
     * one request, which is what a protected property already gives.
     *
     * ⚠️ They MUST be forgotten after any write and whenever the search changes.
     * `placeNode()` writes and Livewire then re-renders the SAME instance, so a
     * cache that survived the write would draw the order the page had BEFORE the
     * move and the user would watch their own drag undo itself.
     *
     * @var array<string, list<Model&TreeNode>>|null
     */
    protected ?array $treeGroupsCache = null;

    /** @var list<int|string>|null */
    protected ?array $treeSearchCache = null;

    protected bool $treeSearchCacheResolved = false;

    /**
     * What to say when the tree draws no rows.
     *
     * ⚠️ TWO sentences, not one (finding F24). "There is nothing here" and
     * "nothing matches what you typed" are different facts, and a tree that says
     * the first while a search is active is simply lying to the actor — a
     * first-time user and someone who mistyped a filter need opposite guidance.
     *
     * The package rendered a single `empty` key and passed the search state
     * nowhere, so a host with both sentences had to pick one and be wrong in the
     * other state. Raised by the first consumer, which has had both strings since
     * before this package existed, and which had **no host-side fix available** —
     * that is what made it the package's problem rather than the host's.
     *
     * ⚠️ Uses the same `trim()` the search path uses. Two different answers to "is
     * a search active?" inside one class is exactly how they drift apart.
     */
    public function treeEmptyMessage(): string
    {
        return trim($this->treeSearch) === ''
            ? (string) __('tree::tree.empty')
            : (string) __('tree::tree.empty_search');
    }

    /**
     * The package's tree view.
     *
     * ⚠️ **A METHOD, not a `$view` property, and this is not a style choice — it is
     * a fatal-error fix found by CI.** Filament declares `protected string $view` on
     * its page classes, and a trait property whose definition differs from one a
     * PARENT declares is a **fatal composition error on PHP 8.3 and 8.4**:
     *
     * ```
     * Filament\Pages\Page and InteractsWithTree define the same property ($view)
     * in the composition of TreePage. However, the definition differs and is
     * considered incompatible.
     * ```
     *
     * PHP 8.5 permits it, which is exactly how this shipped: the rule was probed on
     * one version and generalised. Five of ten CI jobs died at COMPILE time.
     *
     * `BasePage::render()` calls `view($this->getView(), ...)`, so overriding the
     * accessor reaches the same seam — and a trait METHOD beats an INHERITED one on
     * every supported version. Guarded generally by
     * `tests/Bridge/TraitCompositionTest.php`, which fails if this trait ever
     * declares any property a Filament page ancestor also declares.
     */
    public function getView(): string
    {
        return 'tree::tree';
    }

    /**
     * The host's privacy scope. Required.
     *
     * ⚠️ `covariant`, and it is not decoration (finding F25). `Builder`'s template
     * parameter is INVARIANT, so `Builder<Model&TreeNode>` demanded that every host
     * return a builder of exactly that intersection — which no host can, because a
     * host returns `Builder<ItsOwnModel>`. The declaration made a documented,
     * REQUIRED slot impossible to implement for any consumer running static
     * analysis at a useful level, and the package's own suite could not see it
     * because its fixtures are not analysed.
     *
     * Found by the first consumer at PHPStan level 8. Marking the argument
     * covariant says what was always meant: any builder of a tree node will do,
     * because the package only ever READS through it.
     *
     * @return Builder<covariant Model&TreeNode>
     */
    abstract protected function visibleQuery(): Builder;

    /** @return array<int, mixed> */
    protected function badgesFor(Model $node): array
    {
        return [];
    }

    /** @return array<int, mixed> */
    protected function rowActions(Model $node): array
    {
        return [];
    }

    /** @return array<int, mixed> */
    protected function headerActions(): array
    {
        return [];
    }

    protected function leafSlot(Model $node): ?View
    {
        return null;
    }

    /**
     * Return a warning to require confirmation; `null` applies the move immediately.
     */
    protected function confirmationFor(Model $node, ?TreeNode $newParent): ?string
    {
        return null;
    }

    /**
     * May this actor move THIS node to THIS parent? The HOST decides.
     *
     * ⚠️ Requested as package amendment PA-2 by the first real consumer, which
     * found a permission hole: `authorizeMove()` resolved ids through the host's
     * `visibleQuery()` and did nothing else, and **visibility is not permission**.
     * An actor holding `view` and not `update` could see a node, and could
     * therefore move it. The contract claimed this was already checked; the code
     * checked visibility.
     *
     * Defaults to `true`. The package has no idea what a host's permissions are,
     * and it must not invent one (AGENTS.md R-003) — a host that wants a rule
     * writes it here.
     *
     * Two shapes, both deliberate:
     *
     * - **return `false`** for a soft refusal — the actor is told, nothing is
     *   written, and the page stays on screen;
     * - **throw** — typically `$this->authorize('update', $node)`, whose
     *   `AuthorizationException` becomes a 403. The package does not catch it;
     *   `commit()` catches `DomainException`, which is the refusal vocabulary and
     *   deliberately not this.
     *
     * Asked on EVERY committing path — `placeNode()` and `confirmPendingMove()` —
     * because an actor's permissions can change between queueing a move and
     * confirming it.
     */
    protected function authorizeTreeMove(Model $node, ?TreeNode $newParent): bool
    {
        return true;
    }

    /**
     * The announcement templates handed to the Alpine controller.
     *
     * ⚠️ Passed as the ARGUMENT to the x-data factory (research R7), so translation
     * stays server-side and host-controlled. Not a $wire round trip for strings the
     * server already has, and not a JSON blob in a data attribute.
     *
     * @return array<string, string>
     */
    public function treeStrings(): array
    {
        /** @var array<string, string> $strings */
        $strings = trans('tree::tree.announce');

        return is_array($strings) ? $strings : [];
    }

    // ── Reading ──────────────────────────────────────────────────────────────

    /**
     * The visible nodes, grouped by parent key and in read order.
     *
     * The root group is keyed `''` rather than `null`, because PHP array keys
     * cannot be null and a silent cast to `0` would collide with a real key.
     *
     * @return array<string, list<Model&TreeNode>>
     */
    public function nodesByParent(): array
    {
        return $this->treeGroupsCache ??= $this->readNodesByParent();
    }

    /**
     * Forget the per-request caches.
     *
     * Called after every write and whenever the search term changes. Nothing else
     * may invalidate them, because nothing else can change what this actor sees
     * inside a single request.
     */
    protected function forgetTreeCache(): void
    {
        $this->treeGroupsCache = null;
        $this->treeSearchCache = null;
        $this->treeSearchCacheResolved = false;
    }

    public function updatedTreeSearch(): void
    {
        $this->forgetTreeCache();
    }

    /**
     * @return array<string, list<Model&TreeNode>>
     */
    protected function readNodesByParent(): array
    {
        $parentColumn = TreeColumns::parent();
        $tiebreaker = TreeColumns::tiebreaker();

        $query = $this->visibleQuery()
            ->orderBy(TreeColumns::position());

        if ($tiebreaker !== null) {
            $query->orderBy($tiebreaker);
        }

        $visibleIds = $this->searchVisibleIds();

        $grouped = [];

        foreach ($query->get() as $node) {
            if ($visibleIds !== null && ! in_array(SiblingGroup::key($node->getKey()), $visibleIds, strict: true)) {
                continue;
            }

            $parentKey = $node->getAttribute($parentColumn);
            $grouped[$parentKey === null ? '' : (string) $parentKey][] = $node;
        }

        return $grouped;
    }

    /**
     * The rows shown at the top level of what THIS actor can see.
     *
     * ⚠️ Real roots, followed by ORPHANS — nodes the host's query returned whose
     * PARENT it did not. spec.md § Edge Cases requires an orphan to "render at the
     * root of what the actor can see without implying its true parent".
     *
     * Without this an orphan was filed under its parent's key, the render walked
     * only from the root group, and the node vanished completely: invisible to an
     * actor who was explicitly permitted to see it, and therefore unreorderable.
     *
     * Real roots come first because they are the actor's genuine top level;
     * orphan groups follow in ascending parent key. The order matters only in that
     * it must be DETERMINISTIC — an arbitrary order here would make the read order
     * depend on hash iteration.
     *
     * @return list<Model&TreeNode>
     */
    public function treeDisplayRoots(): array
    {
        $grouped = $this->nodesByParent();
        $visible = $this->visibleKeys();

        $roots = $grouped[''] ?? [];

        $orphanKeys = [];

        foreach (array_keys($grouped) as $parentKey) {
            if ($parentKey === '' || in_array(SiblingGroup::key($parentKey), $visible, strict: true)) {
                continue;
            }

            $orphanKeys[] = $parentKey;
        }

        sort($orphanKeys);

        foreach ($orphanKeys as $parentKey) {
            $roots = [...$roots, ...$grouped[$parentKey]];
        }

        return array_values($roots);
    }

    /**
     * The parent key this node is grouped under — `''` for a real root.
     *
     * ⚠️ An orphan keeps its TRUE parent key here even though it displays at the
     * root. The display promotes it; the placement rule must not. It is still a
     * member of its real group, and its order is still resolved inside that group.
     */
    public function treeParentKeyFor(Model $node): string
    {
        $parent = $node->getAttribute(TreeColumns::parent());

        return $parent === null ? '' : (string) $parent;
    }

    /**
     * ⚠️ Counts the RENDERED members of the node's REAL group, never the display
     * list it was promoted into. Reporting an orphan as "1 of 3" alongside the real
     * roots would imply it has no parent — a different disclosure from the one
     * FR-037 prevents, but a disclosure all the same.
     */
    public function treeSetSizeFor(Model $node): int
    {
        return count($this->nodesByParent()[$this->treeParentKeyFor($node)] ?? []);
    }

    public function treePositionFor(Model $node): int
    {
        $group = $this->nodesByParent()[$this->treeParentKeyFor($node)] ?? [];

        foreach (array_values($group) as $index => $sibling) {
            if (SiblingGroup::key($sibling->getKey()) === SiblingGroup::key($node->getKey())) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * @return list<int|string>
     */
    protected function visibleKeys(): array
    {
        $keys = [];

        foreach ($this->nodesByParent() as $group) {
            foreach ($group as $node) {
                $keys[] = SiblingGroup::key($node->getKey());
            }
        }

        return $keys;
    }

    /**
     * The ids a quick search leaves displayed, or `null` when there is no search.
     *
     * ⚠️ Search NARROWS what is displayed; it must never WIDEN what is visible.
     * Every id here came out of the host's own `visibleQuery()`.
     *
     * Matched nodes bring their ancestors with them — a match buried three levels
     * down is unreachable if its parents vanish.
     *
     * @return list<int|string>|null
     */
    public function searchVisibleIds(): ?array
    {
        if ($this->treeSearchCacheResolved) {
            return $this->treeSearchCache;
        }

        $this->treeSearchCacheResolved = true;

        return $this->treeSearchCache = $this->readSearchVisibleIds();
    }

    /**
     * @return list<int|string>|null
     */
    protected function readSearchVisibleIds(): ?array
    {
        $term = trim($this->treeSearch);

        if ($term === '') {
            return null;
        }

        $parentColumn = TreeColumns::parent();
        $nodes = $this->visibleQuery()->get();

        $parents = [];
        foreach ($nodes as $node) {
            $parentValue = $node->getAttribute($parentColumn);
            $parents[(string) SiblingGroup::key($node->getKey())] = $parentValue === null
                ? null
                : SiblingGroup::key($parentValue);
        }

        $keep = [];

        foreach ($nodes as $node) {
            if (! $this->matchesSearch($node, $term)) {
                continue;
            }

            $cursor = SiblingGroup::key($node->getKey());

            while ($cursor !== null && ! isset($keep[(string) $cursor])) {
                $keep[(string) $cursor] = $cursor;
                $cursor = $parents[(string) $cursor] ?? null;
            }
        }

        return array_values($keep);
    }

    protected function matchesSearch(Model $node, string $term): bool
    {
        $tiebreaker = TreeColumns::tiebreaker();
        $haystack = $tiebreaker === null ? '' : (string) $node->getAttribute($tiebreaker);

        return str_contains(mb_strtolower($haystack), mb_strtolower($term));
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    /**
     * ⚠️ Named `placeNode`, never `dropNode`, and the naming is load-bearing.
     *
     * In the source application this method was RENAMED as the safety mechanism
     * when its parameters reordered: a stale positional call would otherwise have
     * stayed syntactically valid and silently meant something else. Named arguments
     * do not catch that — Livewire dispatches through the container's method
     * injection, which matches by name and SILENTLY DISCARDS unknown keys. A
     * renamed method throws before dispatch. If this signature changes, rename it
     * again (`AGENTS.md` R-030).
     *
     * @param  array<int, int|string>  $renderedSiblingIds
     */
    public function placeNode(
        int|string $nodeKey,
        int|string|null $destinationParentKey,
        int|string|null $referenceKey,
        string $placement,
        array $renderedSiblingIds,
    ): void {
        $payload = [
            'nodeKey' => $nodeKey,
            'destinationParentKey' => $destinationParentKey,
            'referenceKey' => $referenceKey,
            'placement' => $placement,
            'renderedSiblingIds' => $renderedSiblingIds,
        ];

        $authorized = $this->authorizeMove($payload);

        if ($authorized === null) {
            return;
        }

        [$node, $parent] = $authorized;

        $confirmation = $this->confirmationFor($node, $parent);

        if ($confirmation !== null) {
            // Nothing is written. The raw payload is held, not the resolved move.
            $this->pendingMove = [...$payload, 'message' => $confirmation];

            return;
        }

        $this->commit($payload);
    }

    public function confirmPendingMove(): void
    {
        $pending = $this->pendingMove;

        $this->pendingMove = null;

        if ($pending === null) {
            return;
        }

        unset($pending['message']);

        // ⚠️ Re-authorized and re-resolved FROM SCRATCH on this call, against a
        // freshly-run visibleQuery. The check made when the move was queued is not
        // the guard — an actor's scope can change between queueing and confirming,
        // which is exactly what happens when permissions change mid-session.
        $this->commit($pending);
    }

    public function cancelPendingMove(): void
    {
        $this->pendingMove = null;
    }

    /**
     * @param  array{nodeKey: int|string, destinationParentKey: int|string|null, referenceKey: int|string|null, placement: string, renderedSiblingIds: array<int, int|string>}  $payload
     */
    protected function commit(array $payload): void
    {
        $authorized = $this->authorizeMove($payload);

        if ($authorized === null) {
            return;
        }

        [$node, $parent, $reference, $placement, $renderedSiblingIds] = $authorized;

        // ⚠️ spec.md § Edge Cases: "A group contains exactly one node. Every
        // reorder request is a no-op that must be REPORTED, not silently
        // accepted." The keyboard already announced this while a node was held;
        // the pointer did not, and worse, it still wrote and still fired
        // NodeMoved — recording a move in the host's audit trail that the user
        // never made.
        //
        // Scoped to a REORDER. Being an only child does not make a RE-PARENT a
        // no-op, and a guard broad enough to refuse that would refuse real work.
        if ($this->isOnlyChildReorder($node, $parent)) {
            // ⚠️ The SAME token set the Alpine controller passes for this key
            // (PA-4). `only_child` is the one announcement raised from both the
            // client and the server, and a key that substituted `:total` in the
            // browser but not here would be the very defect PA-4 closes, reachable
            // through whichever producer the host happened not to exercise.
            $this->report((string) __('tree::tree.announce.only_child', [
                'name' => $this->treeNameFor($node),
                'position' => $this->treePositionFor($node),
                'total' => $this->treeSetSizeFor($node),
            ]));

            return;
        }

        try {
            if ($this->isSameParentReorder($node, $parent)) {
                // ⚠️ Package amendment PA-3. A same-parent placement is a REORDER,
                // and reordering is what `ReorderSiblings` is for: it fires
                // `SiblingsReordered` carrying the whole written order, which is
                // the shape a host's audit trail wants — "these five, in this
                // order", performed on the parent. Routing it through `PlaceNode`
                // recorded it as a `NodeMoved` instead, and left the package
                // shipping a public event with no producer anywhere in the bridge.
                $this->reorder($node, $parent, $reference, $placement, $renderedSiblingIds);
            } else {
                app(PlaceNode::class)->handle(
                    $node,
                    $parent,
                    $reference,
                    $placement,
                    $renderedSiblingIds,
                );
            }
        } catch (DomainException $exception) {
            $this->refuse($exception->getMessage());
        } finally {
            // ⚠️ In `finally`, not after the call. A REFUSED move can still have
            // been preceded by a successful one in the same request, and a cache
            // left standing here would render a stale tree either way.
            $this->forgetTreeCache();
        }
    }

    /**
     * Re-run the host's scope and reject anything the actor could not have aimed at.
     *
     * ⚠️ `$renderedSiblingIds` arrives FROM THE CLIENT and is intersected with what
     * the host's query returns right now. Trusting it would hand a tampered payload
     * the ability to address a node privacy hides — which is the authorization half
     * of constitution Principle II.
     *
     * @param  array{nodeKey: int|string, destinationParentKey: int|string|null, referenceKey: int|string|null, placement: string, renderedSiblingIds: array<int, int|string>}  $payload
     * @return array{0: Model&TreeNode, 1: (Model&TreeNode)|null, 2: (Model&TreeNode)|null, 3: SiblingPlacement, 4: list<int|string>}|null
     */
    protected function authorizeMove(array $payload): ?array
    {
        $placement = $this->placementFrom($payload['placement']);

        if ($placement === null) {
            return null;
        }

        $visible = $this->visibleQuery()->get()->keyBy(
            fn (Model $node): string => (string) SiblingGroup::key($node->getKey())
        );

        $node = $visible->get((string) SiblingGroup::key($payload['nodeKey']));

        if (! $node instanceof Model || ! $node instanceof TreeNode) {
            return null;
        }

        $parent = null;

        if ($payload['destinationParentKey'] !== null) {
            $parent = $visible->get((string) SiblingGroup::key($payload['destinationParentKey']));

            if (! $parent instanceof Model || ! $parent instanceof TreeNode) {
                return null;
            }
        }

        // ⚠️ PA-2, and the POSITION of this call is load-bearing.
        //
        // AFTER the node and the destination are resolved from the host's own
        // scope, so the host is handed real models rather than client-supplied
        // ids — a slot given the raw payload would make every host re-implement
        // the visibility check to answer safely.
        //
        // BEFORE any reference work, so an actor who may not move this node
        // learns nothing about which neighbours exist.
        if (! $this->authorizeTreeMove($node, $parent)) {
            $this->refuse((string) __('tree::tree.refused.unauthorized', [
                'name' => $this->treeNameFor($node),
            ]));

            return null;
        }

        $reference = null;

        if ($payload['referenceKey'] !== null) {
            $candidate = $visible->get((string) SiblingGroup::key($payload['referenceKey']));

            // An invisible reference is NOT rejected here with a bespoke message:
            // it is simply not resolved, and the placement rule then refuses it
            // through the one path every unreachable reference takes.
            $reference = $candidate instanceof TreeNode ? $candidate : null;
        }

        $renderedSiblingIds = [];

        foreach ($payload['renderedSiblingIds'] as $id) {
            $key = SiblingGroup::key($id);

            if ($visible->has((string) $key)) {
                $renderedSiblingIds[] = $key;
            }
        }

        if ($placement->needsReference() && $reference === null) {
            $this->refuse((string) __('tree::tree.refused.unreachable_reference'));

            return null;
        }

        return [$node, $parent, $reference, $placement, $renderedSiblingIds];
    }

    /**
     * Is this placement a reorder within the group the node is already in?
     *
     * ⚠️ Compares the node's STORED parent, never the group the render filed it
     * under. This decision drives a WRITE, and an orphan is displayed at the root
     * while still belonging to its real group — deciding from the display would
     * rewrite the wrong group's order entirely.
     */
    protected function isSameParentReorder(Model $node, ?TreeNode $parent): bool
    {
        $destinationKey = $parent === null ? '' : (string) SiblingGroup::key($parent->getKey());
        $currentParent = $node->getAttribute(TreeColumns::parent());

        return $destinationKey === ($currentParent === null ? '' : (string) $currentParent);
    }

    /**
     * Rewrite this node's own group so it lands beside the neighbour it named.
     *
     * ⚠️ The index still comes from `ResolveSiblingPlacement`, resolved against the
     * COMPLETE group — exactly as `PlaceNode` would compute it. This method names a
     * neighbour and asks the package where that falls; it never counts rows itself
     * (constitution Principle II, `AGENTS.md` R-007).
     *
     * @param  list<int|string>  $renderedSiblingIds
     */
    protected function reorder(
        Model&TreeNode $node,
        ?TreeNode $parent,
        ?TreeNode $reference,
        SiblingPlacement $placement,
        array $renderedSiblingIds,
    ): void {
        // ⚠️ Asked before the write, and deliberately duplicating the question
        // `MoveNode` asks on the path this one replaces. PA-3 changes which action
        // runs; it must not change which moves are PERMITTED. Without this, a
        // reorder inside a parent the host says cannot receive children would
        // start succeeding — a silent widening nobody requested. `MoveNode` keeps
        // its own copy for direct callers, so both are load-bearing and neither is
        // redundant with the other.
        if ($parent !== null && ! $parent->isValidTreeTarget()) {
            throw InvalidTargetException::make();
        }

        $index = app(ResolveSiblingPlacement::class)->handle(
            $node,
            $parent,
            $reference,
            $placement,
            $renderedSiblingIds,
        );

        // The complete group in read order with the moved node removed — the very
        // list that index was resolved against. There is exactly one copy of the
        // read order in this package and it lives in `SiblingGroup`, so this cannot
        // drift from what `ResolveSiblingPlacement` saw.
        $ordered = SiblingGroup::for($node, $parent, $node->getKey());

        // Clamped for the same reason `MoveNode` clamps: the index came from this
        // same group, so an out-of-range value can only mean the group changed
        // underneath us, and landing at the end is the safe answer.
        array_splice($ordered, max(0, min($index, count($ordered))), 0, [SiblingGroup::key($node->getKey())]);

        app(ReorderSiblings::class)->handle(
            $parent,
            $ordered,
            // ⚠️ Named for the ROOT case, which is the entire reason `$model` was
            // added to that signature: root keys carry no model class and there is
            // no parent to infer one from, so the action refuses rather than guess.
            $node::class,
        );
    }

    /**
     * Is this request a reorder inside a group whose only member is this node?
     */
    protected function isOnlyChildReorder(Model $node, ?TreeNode $parent): bool
    {
        $destinationKey = $parent === null ? '' : (string) SiblingGroup::key($parent->getKey());

        if ($destinationKey !== $this->treeParentKeyFor($node)) {
            // A re-parent, not a reorder. Nothing to report.
            return false;
        }

        return count($this->nodesByParent()[$destinationKey] ?? []) <= 1;
    }

    /**
     * The name shown for a node — the configured tie-breaker column, which is what
     * the row renders and therefore what the actor would recognise.
     */
    protected function treeNameFor(Model $node): string
    {
        $column = TreeColumns::tiebreaker() ?? $node->getKeyName();

        return (string) $node->getAttribute($column);
    }

    /**
     * Tell the actor something that is not a refusal.
     *
     * ⚠️ Dispatched to the live region AND shown as a notification. A pointer user
     * never hears the live region, and a screen-reader user should not depend on a
     * toast; reporting to one surface only leaves half the audience uninformed.
     */
    protected function report(string $message): void
    {
        $this->dispatch('ltree-announce', message: $message);

        Notification::make()
            ->title($message)
            ->info()
            ->send();
    }

    protected function placementFrom(string $placement): ?SiblingPlacement
    {
        foreach (SiblingPlacement::cases() as $case) {
            if ($case->name === $placement) {
                return $case;
            }
        }

        return null;
    }

    protected function refuse(string $message): void
    {
        $this->dispatch('ltree-announce', message: $message);

        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    // ── Host slots, exposed to the views ─────────────────────────────────────

    /** @return array<int, mixed> */
    public function treeBadgesFor(Model $node): array
    {
        return $this->badgesFor($node);
    }

    /** @return array<int, mixed> */
    public function treeRowActions(Model $node): array
    {
        return $this->rowActions($node);
    }

    public function treeLeafSlot(Model $node): ?View
    {
        return $this->leafSlot($node);
    }

    /**
     * ⚠️ Also declared on `Filament\Pages\Concerns\InteractsWithHeaderActions`,
     * which both Filament base pages use. PHP resolves a TRAIT method ahead of an
     * INHERITED one, so this wins and the host's `headerActions()` slot is reached
     * — checked explicitly in `tests/Bridge/InteractsWithTreeTest.php` rather than
     * assumed, because it is a claim about language semantics that a host would
     * otherwise discover as a missing toolbar.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return $this->headerActions();
    }
}

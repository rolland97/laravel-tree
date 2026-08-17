<?php

declare(strict_types=1);

namespace Rolland\Tree\Filament\Pages;

use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Contracts\TreeNode;
use Rolland\Tree\Enums\SiblingPlacement;
use Rolland\Tree\Support\SiblingGroup;
use Rolland\Tree\Support\TreeColumns;

/**
 * An accessible drag-and-keyboard tree page. A host extends this.
 *
 * ⚠️ The host supplies `$model` and `visibleQuery()`. THE PACKAGE NEVER ADDS A
 * PRIVACY SCOPE — it owns the ordering rule, the host owns who may see what
 * (constitution Principle III, AGENTS.md R-003).
 *
 * @property-read string $treeSearch
 */
abstract class TreePage extends Page
{
    /** @var class-string<Model&TreeNode> */
    protected static string $model;

    protected string $view = 'tree::tree';

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
     * The host's privacy scope. Required.
     *
     * @return Builder<Model&TreeNode>
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

        try {
            app(PlaceNode::class)->handle(
                $node,
                $parent,
                $reference,
                $placement,
                $renderedSiblingIds,
            );
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

    /** @return array<int, mixed> */
    protected function getHeaderActions(): array
    {
        return $this->headerActions();
    }

    /** @return class-string<Model&TreeNode> */
    public static function treeModel(): string
    {
        return static::$model;
    }
}

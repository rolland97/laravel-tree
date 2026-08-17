<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\MoveCounter;
use Rolland\Tree\Tests\Fixtures\Panel\AnnouncementTreePage;

/**
 * PA-4 — the announcement PLACEHOLDER contract.
 *
 * Four keys' templates were handed only `:name`, so a host whose wording used
 * `:position` or `:total` had the literal text `":position"` announced to a
 * screen-reader user (the consumer's adoption, research F10):
 *
 * | key | wanted | substituted |
 * |---|---|---|
 * | `picked_up` | `:name :position :total` | `:name` |
 * | `cancelled` | `:name :position :total` | `:name` |
 * | `already_first` | `:name :total` | `:name` |
 * | `already_last` | `:name :total` | `:name` |
 *
 * ⚠️ **The package's own sweep cannot catch this**, and that is structural rather
 * than an oversight: `HostOverridesCopyTest` checks the package's lang file against
 * the package's token list, and the package's own wording does not use the missing
 * tokens. The defect exists only once a host overrides — so the guard has to be a
 * host override, which is what `AnnouncementTreePage` is.
 *
 * ⚠️ This renders the live region's text: evidence for the placeholder contract,
 * and explicitly NOT for SC-011. It proves the DATA a reader is handed, never that
 * the sentences work as heard (AGENTS.md R-018).
 */
beforeEach(function () {
    MoveCounter::reset();

    // Three siblings, positional order the reverse of alphabetical (R-025).
    $this->root = Category::create(['name' => 'Root', 'position' => 0]);
    $this->delta = Category::create(['name' => 'Delta', 'parent_id' => $this->root->id, 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->root->id, 'position' => 1]);
    $this->bravo = Category::create(['name' => 'Bravo', 'parent_id' => $this->root->id, 'position' => 2]);
});

/** Every token the contract lists, so a survivor of any of them fails. */
function assertNoLiteralTokens(mixed $announced): void
{
    expect((string) $announced)
        ->not->toContain(':name')
        ->not->toContain(':position')
        ->not->toContain(':total')
        ->not->toContain(':parent');
}

it('substitutes position and total when a node is picked up', function () {
    $page = visit('/admin/announcement-tree');
    focusRowByKey($page, $this->charlie->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    $announced = announcement($page);

    // Charlie is the SECOND of three in positional order.
    expect((string) $announced)->toContain('HOST picked up Charlie, 2 of 3.');
    assertNoLiteralTokens($announced);
});

it('substitutes total when a node is already first', function () {
    $page = visit('/admin/announcement-tree');
    focusRowByKey($page, $this->delta->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);

    $announced = announcement($page);

    expect((string) $announced)->toContain('HOST Delta is already first of 3.');
    assertNoLiteralTokens($announced);
});

it('substitutes total when a node is already last', function () {
    $page = visit('/admin/announcement-tree');
    focusRowByKey($page, $this->bravo->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowDown');
    settleAnnouncement($page);

    $announced = announcement($page);

    expect((string) $announced)->toContain('HOST Bravo is already last of 3.');
    assertNoLiteralTokens($announced);
});

it('substitutes the ORIGINAL position and total when a held move is cancelled', function () {
    // ⚠️ The position announced on cancel is where the node RETURNS to, which is
    // where it started — not where the abandoned move had walked it to. Announcing
    // the latter would tell a non-sighted user the node is somewhere it is not.
    $page = visit('/admin/announcement-tree');
    focusRowByKey($page, $this->bravo->id);

    sendKey($page, ' ');
    settleAnnouncement($page);
    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    sendKey($page, 'Escape');
    settleAnnouncement($page);

    $announced = announcement($page);

    // Bravo started third of three, and walking up then cancelling returns it there.
    expect((string) $announced)->toContain('HOST cancelled Bravo, returned to 3 of 3.');
    assertNoLiteralTokens($announced);
});

it('leaves no literal token in any announcement of a completed keyboard move', function () {
    // The keys that already substituted correctly, re-checked through a host
    // override so a regression in `moved` or `put_down` cannot hide behind the
    // package's own wording.
    $page = visit('/admin/announcement-tree');
    focusRowByKey($page, $this->bravo->id);

    sendKey($page, ' ');
    settleAnnouncement($page);

    sendKey($page, 'ArrowUp');
    settleAnnouncement($page);
    assertNoLiteralTokens(announcement($page));
    expect((string) announcement($page))->toContain('HOST moved Bravo to 2 of 3.');

    sendKey($page, 'Enter');
    settleAnnouncement($page);
    assertNoLiteralTokens(announcement($page));
    expect((string) announcement($page))->toContain('HOST put down Bravo at 2 of 3.');
});

<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * PA-14 — focus survives the tree's own re-render.
 *
 * ⚠️ Every write goes through Livewire, so the rows are morphed immediately after any
 * move. Morphing replaces the row that had focus, focus falls to `<body>`, and a
 * keyboard user is put back at the top of the document after every single reorder —
 * having to tab back in to make a second move. The roving tabindex still SAID the row
 * owned the tab stop; nothing held the DOM focus.
 *
 * ⚠️ The package's morph hook existed and did nothing: it checked which component had
 * morphed and then had no body at all. That check was written for a real defect (a
 * host's notification poll abandoning a held node), and the "do nothing on a foreign
 * morph" half is right — but nothing was ever done on OUR morph either.
 *
 * ⚠️ Focus is restored ONLY if it was lost. Stealing it back from wherever the actor
 * has since moved — a row action, a modal, the search box — would be a worse defect
 * than the one being fixed, and it is the obvious way to write this wrong.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';
    CategoryTreePage::$immovableName = null;

    $this->parent = Category::create(['name' => 'Parent', 'position' => 0]);
    $this->first = Category::create(['name' => 'First', 'parent_id' => $this->parent->id, 'position' => 0]);
    $this->second = Category::create(['name' => 'Second', 'parent_id' => $this->parent->id, 'position' => 1]);
});

function focusMorphKeyOfActiveElement(object $page): mixed
{
    return $page->script('document.activeElement?.dataset?.ltreeKey ?? null');
}

function focusMorphRefresh(object $page): void
{
    // ⚠️ Research R11, trap 2: `$wire.$refresh()` returns a promise that NEVER
    // resolves when awaited inside a page evaluation. Voided, never awaited.
    $page->script(
        "(() => { const el = document.querySelector('.ltree-root');"
        ." const id = el.closest('[wire\\\\:id]')?.getAttribute('wire:id');"
        .' if (id && window.Livewire) { void window.Livewire.find(id).$refresh(); } })()'
    );
    $page->script('(async () => { await new Promise(r => setTimeout(r, 900)); return true; })()');
}

it('keeps focus on the row that had it across a re-render', function () {
    $page = visit('/admin/category-tree');

    $page->assertPresent('[data-ltree-key="'.$this->first->id.'"]');
    $page->script("document.querySelector('[data-ltree-key=\"{$this->first->id}\"]').focus()");

    expect(focusMorphKeyOfActiveElement($page))->toBe((string) $this->first->id);

    focusMorphRefresh($page);

    expect(focusMorphKeyOfActiveElement($page))->toBe((string) $this->first->id);
});

it('keeps focus on the moved row after a keyboard move commits', function () {
    // ⚠️ THE case that matters. A move is a write, so it always morphs — this is the
    // one path every keyboard user takes, every time.
    $page = visit('/admin/category-tree');

    $page->script("document.querySelector('[data-ltree-key=\"{$this->second->id}\"]').focus()");

    foreach ([' ', 'ArrowUp', 'Enter'] as $key) {
        $page->script(
            '(() => { document.activeElement?.dispatchEvent('
            ."new KeyboardEvent('keydown', { key: '{$key}', bubbles: true, cancelable: true })); })()"
        );
        $page->script('(async () => { await new Promise(r => setTimeout(r, 400)); return true; })()');
    }

    // The write happened…
    expect(Category::query()->find($this->second->id)->position)->toBe(0);

    // …and the actor is still on the row they moved, ready to move it again.
    expect(focusMorphKeyOfActiveElement($page))->toBe((string) $this->second->id);
});

it('does not steal focus back from a control the actor moved to', function () {
    // ⚠️ The half that makes the fix safe rather than merely present.
    $page = visit('/admin/category-tree');

    $page->script("document.querySelector('[data-ltree-key=\"{$this->first->id}\"]').focus()");
    $page->script("document.querySelector('input[type=\"search\"]').focus()");

    focusMorphRefresh($page);

    expect($page->script('document.activeElement?.getAttribute("type")'))->toBe('search');
});

it('leaves focus alone when the tree never had it', function () {
    $page = visit('/admin/category-tree');

    $page->script('document.body.focus()');

    focusMorphRefresh($page);

    expect(focusMorphKeyOfActiveElement($page))->toBeNull();
});

<?php

declare(strict_types=1);

use Livewire\Livewire;
use Rolland\Tree\Tests\Fixtures\Category;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;

/**
 * PA-10 — a `treeitem` is a DIRECT child of the `tree` or of a `group`.
 *
 * ⚠️ The rendered markup wrapped every row and its children in a `.ltree-branch`
 * div, so `role="tree"` owned generic divs and the rows were its GRANDchildren. ARIA
 * gives `tree` required owned elements — `treeitem` and `group` — and an unroled
 * element in between is not one of them.
 *
 * ⚠️ **axe does not report this**, and that is the reason it survived: the package's
 * own `assertNoAccessibilityIssues()` passes with the wrapper in place, because axe's
 * required-children check walks ANCESTORS rather than demanding a direct child. So an
 * automated pass proved nothing here, exactly as `AGENTS.md` R-018 says about
 * announcements, and the defect was found by a consumer whose own frozen selectors
 * were written against `[role="tree"] > [role="treeitem"]` (the consumer's adoption, T048).
 *
 * ⚠️ Asserted by WALKING THE DOM, not by matching markup. A string search for
 * `ltree-branch` would go green the moment the class was renamed while the structure
 * stayed wrong.
 */
beforeEach(function () {
    CategoryTreePage::$hideBravo = false;
    CategoryTreePage::$moveAuthorization = 'allow';

    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->charlie = Category::create(['name' => 'Charlie', 'parent_id' => $this->delta->id, 'position' => 0]);
    $this->echo = Category::create(['name' => 'Echo', 'parent_id' => $this->charlie->id, 'position' => 0]);
});

/** @return list<string> the offending rows, named, so a failure says which */
function ariaStructureOrphanRows(string $html): array
{
    $dom = new DOMDocument;
    $dom->loadHTML($html, LIBXML_NOERROR);

    $xpath = new DOMXPath($dom);
    $offenders = [];

    /** @var DOMElement $row */
    foreach ($xpath->query('//*[@role="treeitem"]') as $row) {
        $parent = $row->parentNode;
        $parentRole = $parent instanceof DOMElement ? $parent->getAttribute('role') : '';

        if (in_array($parentRole, ['tree', 'group'], strict: true)) {
            continue;
        }

        $offenders[] = $row->getAttribute('data-ltree-key').' under <'
            .($parent instanceof DOMElement ? $parent->tagName : '?')
            .' role="'.$parentRole.'">';
    }

    return $offenders;
}

it('puts every row directly inside the tree or a group', function () {
    $offenders = ariaStructureOrphanRows(Livewire::test(CategoryTreePage::class)->html());

    expect($offenders)->toBe([]);
});

it('checks rows at every depth, not just the roots', function () {
    // ⚠️ The fixture is three levels deep on purpose. A wrapper only at the root
    // would be caught by the case above; one only under a group would not, and the
    // nesting is where the wrapper actually lived.
    $html = Livewire::test(CategoryTreePage::class)->html();

    $dom = new DOMDocument;
    $dom->loadHTML($html, LIBXML_NOERROR);

    $deepest = (new DOMXPath($dom))->query('//*[@aria-level="3"][@role="treeitem"]');

    expect($deepest->length)->toBe(1);
    expect(ariaStructureOrphanRows($html))->toBe([]);
});

it('keeps each group directly inside the row\'s own subtree position', function () {
    // The children container stays a SIBLING of its row rather than becoming a
    // child of it — a `treeitem` may own a `group`, but nesting the group inside the
    // focusable row would put the whole subtree inside the row's accessible name,
    // which is the R-014 defect this package was extracted to fix.
    $dom = new DOMDocument;
    $dom->loadHTML(Livewire::test(CategoryTreePage::class)->html(), LIBXML_NOERROR);

    $groups = (new DOMXPath($dom))->query('//*[@role="group"]');
    $nestedInsideARow = [];

    /** @var DOMElement $group */
    foreach ($groups as $group) {
        $parent = $group->parentNode;

        if ($parent instanceof DOMElement && $parent->getAttribute('role') === 'treeitem') {
            $nestedInsideARow[] = $group->getAttribute('data-ltree-children-of');
        }
    }

    expect($groups->length)->toBeGreaterThan(0);
    expect($nestedInsideARow)->toBe([]);
});

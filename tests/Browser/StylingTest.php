<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Category;

/**
 * T062 — the package's styling, verified against COMPUTED values in a real
 * browser, in light AND dark, in a panel with no custom theme.
 *
 * ⚠️ tasks.md assumed this could not be asserted, because "the harness serves no
 * compiled CSS". The R10 spike disproved that for this harness: the panel serves
 * `resources/dist/tree.css` and the browser applies it. So these checks are real
 * rather than vacuous — which is a better outcome than the plan expected, and the
 * reason the spike was worth running before US2 rather than after.
 *
 * ⚠️ Every probe reads a property whose DEFAULT differs from the asserted value.
 * A probe on a bare element reading `outline-style` cannot fail, because its
 * default is already `none` — that is precisely how the source application
 * concluded a utility had compiled while no CSS was being served at all
 * (quickstart.md § SC-005).
 */
beforeEach(function () {
    $this->delta = Category::create(['name' => 'Delta', 'position' => 0]);
    $this->alpha = Category::create(['name' => 'Alpha', 'position' => 1]);
});

/** Computed style of the first tree row, after forcing a class onto it. */
function computedRowStyle(object $page, string $property, ?string $addClass = null): mixed
{
    $add = $addClass === null ? '' : "row.classList.add('{$addClass}');";

    return $page->script(
        "(() => { const row = document.querySelector('[data-ltree-key]'); {$add}"
        ." return getComputedStyle(row).getPropertyValue('{$property}'); })()"
    );
}

it('gives the focus ring an explicit outline STYLE, not merely a width and a colour', function () {
    // ⚠️ THE trap this project has already paid for. `outline-none` zeroes
    // `outline-style` while a width utility only sets width — the ring then has a
    // width and a colour and is invisible. `none` is the default, so asserting
    // `solid` is an assertion that can actually fail.
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    expect(computedRowStyle($page, 'outline-style', 'ltree-focused'))->toBe('solid');
});

it('gives the focus ring a non-zero width', function () {
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    expect(computedRowStyle($page, 'outline-width', 'ltree-focused'))->toBe('2px');
});

it('paints the row background in light mode', function () {
    // Default background is transparent — `rgba(0, 0, 0, 0)` — so a served
    // stylesheet is the only way this value can appear.
    $page = visit('/admin/category-tree')->inLightMode()->assertPresent('[data-ltree-key]');

    expect(computedRowStyle($page, 'background-color'))->toBe('rgb(255, 255, 255)');
});

it('paints the row background differently in dark mode', function () {
    $page = visit('/admin/category-tree')->inDarkMode()->assertPresent('[data-ltree-key]');

    expect(computedRowStyle($page, 'background-color'))->toBe('rgb(39, 39, 42)');
});

it('re-colours the confirmation panel in dark mode instead of leaving it white on white', function () {
    // ⚠️ A REGRESSION GUARD for a defect that actually shipped into the working
    // tree: an editing slip spliced the confirmation panel's LIGHT rule inside the
    // dark-mode media query, so the panel kept `background-color: rgb(255 255 255)`
    // on a dark page — present, focusable and unreadable.
    //
    // Nothing else would have caught it. The bridge suite asserts the panel's
    // behaviour, the axe pass reports no violation for it, and no ordering test
    // touches colour at all.
    $page = visit('/admin/category-tree')->inDarkMode()->assertPresent('[data-ltree-key]');

    $background = $page->script(
        "(() => { const el = document.createElement('div'); el.className = 'ltree-confirm';"
        .' document.body.appendChild(el);'
        ." return getComputedStyle(el).getPropertyValue('background-color'); })()"
    );

    expect($background)->toBe('rgb(39, 39, 42)');
});

it('paints the confirmation panel light in light mode', function () {
    $page = visit('/admin/category-tree')->inLightMode()->assertPresent('[data-ltree-key]');

    $background = $page->script(
        "(() => { const el = document.createElement('div'); el.className = 'ltree-confirm';"
        .' document.body.appendChild(el);'
        ." return getComputedStyle(el).getPropertyValue('background-color'); })()"
    );

    expect($background)->toBe('rgb(255, 255, 255)');
});

it('clips the live region rather than hiding it, so it is still announced', function () {
    // ⚠️ `display: none` on a live region removes it from the accessibility tree
    // and nothing is ever announced. The region must be clipped instead.
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    $display = $page->script(
        "getComputedStyle(document.querySelector('.ltree-live-region')).getPropertyValue('display')"
    );

    expect($display)->not->toBe('none');
});

it('reports zero critical accessibility issues on the real tree page, in both schemes', function () {
    visit('/admin/category-tree')->inLightMode()->assertNoAccessibilityIssues();
    visit('/admin/category-tree')->inDarkMode()->assertNoAccessibilityIssues();
});

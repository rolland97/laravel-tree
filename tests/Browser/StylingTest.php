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

// ── FR-030 / R-021 — the accent is INHERITED, not defined ───────────────────

it('paints the focus ring in the host panel\'s OWN accent, whatever syntax it uses', function () {
    // ⚠️ Compared against the variable's ACTUAL value, read from the same page —
    // not against a value this test supplied.
    //
    // The first version of this guard set `--primary-600: 220 38 38` itself and
    // asserted the ring became `rgb(220, 38, 38)`. It passed, and it was WRONG:
    // Filament v5 sets the accent as `oklch(...)`, so the shipped
    // `rgb(var(--primary-600, …))` expanded to `rgb(oklch(...))`, was discarded as
    // invalid, and the ring silently fell back to currentColor on every real
    // install. The test had supplied the one format that made the bug invisible.
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    $result = $page->script(
        '(() => { const root = getComputedStyle(document.documentElement);'
        ." const accent = root.getPropertyValue('--primary-600').trim();"
        ." const row = document.querySelector('[data-ltree-key]');"
        ." row.classList.add('ltree-focused');"
        .' const ring = getComputedStyle(row).outlineColor;'
        .' return { accent, ring, text: getComputedStyle(row).color }; })()'
    );

    expect($result['accent'])->not->toBe('', 'the panel defines no --primary-600 to inherit');
    expect($result['ring'])->toBe($result['accent']);

    // ⚠️ And explicitly NOT the text colour. That is what an invalid declaration
    // degrades to, and it is indistinguishable from "no rule at all".
    expect($result['ring'])->not->toBe($result['text']);
});

it('paints the drop target in the host accent, not in currentColor', function () {
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    $result = $page->script(
        '(() => { const root = getComputedStyle(document.documentElement);'
        ." const accent = root.getPropertyValue('--primary-500').trim();"
        ." const row = document.querySelector('[data-ltree-key]');"
        ." row.classList.add('ltree-drop-target');"
        .' return { accent, border: getComputedStyle(row).borderTopColor,'
        .'          text: getComputedStyle(row).color }; })()'
    );

    expect($result['accent'])->not->toBe('');
    expect($result['border'])->toBe($result['accent']);
    expect($result['border'])->not->toBe($result['text']);
});

it('still follows the accent when a host overrides it at runtime', function () {
    // The other direction: a host that changes its accent must move the ring with
    // it. Uses a full colour value, which is what a host actually sets.
    $page = visit('/admin/category-tree')->assertPresent('[data-ltree-key]');

    $ring = $page->script(
        "(() => { document.documentElement.style.setProperty('--primary-600', 'rgb(220, 38, 38)');"
        ." const row = document.querySelector('[data-ltree-key]');"
        ." row.classList.add('ltree-focused');"
        .' return getComputedStyle(row).outlineColor; })()'
    );

    expect($ring)->toBe('rgb(220, 38, 38)');
});

// ── PA-17 / F38 — the theme is a CLASS, and the OS preference is not it ──────

/*
 * ⚠️ Why the four guards above could not fail.
 *
 * `inDarkMode()` emulates the OPERATING SYSTEM preference, and Filament's own
 * theme script follows that preference when the actor has expressed none — so the
 * harness's dark mode sets `prefers-color-scheme: dark` AND `<html class="fi dark">`
 * at the same time, and the two mechanisms agree in every case the suite ran.
 *
 * They disagree the moment an actor CHOOSES a theme, which is the entire point of
 * a theme switcher. Measured in a real consumer panel (the consumer's adoption T057) and
 * then reproduced here: OS light + the actor picking dark leaves the package's
 * light rules winning while Filament's dark text colour is inherited — a white row
 * with white text on it.
 *
 * These guards drive the class directly, because the class is what Filament sets.
 */

/** Computed style of the first row with the panel's theme class forced on or off. */
function computedRowStyleForTheme(object $page, string $property, bool $dark): mixed
{
    $toggle = $dark ? 'add' : 'remove';

    return $page->script(
        "(() => { document.documentElement.classList.{$toggle}('dark');"
        .' const row = document.querySelector(\'[data-ltree-key]\');'
        ." return getComputedStyle(row).getPropertyValue('{$property}'); })()"
    );
}

it('follows the panel theme CLASS into dark even when the operating system is light', function () {
    $page = visit('/admin/category-tree')->inLightMode()->assertPresent('[data-ltree-key]');

    expect(computedRowStyleForTheme($page, 'background-color', dark: true))->toBe('rgb(39, 39, 42)');
});

it('follows the panel theme class back to LIGHT even when the operating system is dark', function () {
    // The inverse, and just as wrong: an actor on a dark machine who picks the
    // light theme must not get a dark row painted onto a light page.
    $page = visit('/admin/category-tree')->inDarkMode()->assertPresent('[data-ltree-key]');

    expect(computedRowStyleForTheme($page, 'background-color', dark: false))->toBe('rgb(255, 255, 255)');
});

it('never paints a row in the same colour as the text on it', function () {
    // ⚠️ This is the HARM, asserted directly rather than via one of its causes.
    // The shipped defect was not "the wrong grey" — it was `rgb(255, 255, 255)` on
    // `rgb(255, 255, 255)`, a contrast ratio of 1:1, with the category name simply
    // not rendered. A guard on the background alone would pass the day someone
    // changes the palette and reintroduces the collision with different values.
    $page = visit('/admin/category-tree')->inLightMode()->assertPresent('[data-ltree-key]');

    $result = $page->script(
        '(() => { document.documentElement.classList.add(\'dark\');'
        .' const row = document.querySelector(\'[data-ltree-key]\');'
        .' const style = getComputedStyle(row);'
        .' return { bg: style.backgroundColor, text: style.color }; })()'
    );

    expect($result['bg'])->not->toBe($result['text']);
});

it('re-colours the confirmation panel for the theme CLASS, not the operating system', function () {
    $page = visit('/admin/category-tree')->inLightMode()->assertPresent('[data-ltree-key]');

    $background = $page->script(
        "(() => { document.documentElement.classList.add('dark');"
        ." const el = document.createElement('div'); el.className = 'ltree-confirm';"
        .' document.body.appendChild(el);'
        ." return getComputedStyle(el).getPropertyValue('background-color'); })()"
    );

    expect($background)->toBe('rgb(39, 39, 42)');
});

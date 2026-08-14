<?php

declare(strict_types=1);

/**
 * T043–T046 — the R10 spike.
 *
 * ⚠️ Research R10 was `[open]` and blocked US2, US3 and US4. The source
 * application's browser tests run against a full Laravel application, which is
 * NOT evidence the same works from inside a package. Four questions, in order of
 * how expensive it would be to discover the answer late:
 *
 *   1. can a package serve a Filament panel to a browser driver at all? (T044)
 *   2. does that panel serve COMPILED CSS? (T046) — if not, every styling and
 *      accessibility assertion in three stories passes vacuously
 *   3. can axe be run against it, in BOTH colour schemes? (T045)
 *   4. does the served page carry the package's own JavaScript?
 */
it('serves a Filament panel from inside a package to a real browser', function () {
    // T044 — the question that gates everything else.
    $page = visit('/admin/spike');

    $page->assertSee('R10 spike')
        ->assertPresent('#ltree-spike-row');
});

it('serves the spike page itself rather than a 404 that would pass every other check', function () {
    // ⚠️ Kept as its own case after a near miss: with the page registered too late
    // to be routed, `/admin/spike` returned a 404 — and BOTH accessibility
    // assertions below passed against it, because an error page has no
    // accessibility issues. A vacuous green is worse than a red.
    $title = visit('/admin/spike')->script('document.title');

    expect($title)->toContain('R10 spike');
});

it('serves the package\'s COMPILED stylesheet, not merely a link to one', function () {
    // ⚠️ T046, and the one that decides whether US2–US4 are provable at all.
    //
    // The probe reads `background-color`. A probe reading `outline-style` on a
    // bare element CANNOT FAIL — its default is already `none` — which is exactly
    // how the source application concluded a utility had compiled while the dev
    // server was serving no CSS whatsoever (quickstart.md § SC-005). The default
    // background is transparent, so `rgb(185, 28, 28)` can only appear if
    // resources/dist/tree.css actually arrived and applied.
    $page = visit('/admin/spike');

    $background = $page->script(
        "getComputedStyle(document.getElementById('ltree-spike-probe')).backgroundColor"
    );

    expect($background)->toBe('rgb(185, 28, 28)');
});

it('reports zero critical accessibility issues in light mode', function () {
    // T045. ⚠️ This proves LESS than it looks: axe checks that a name EXISTS, not
    // that it is sensible. It cannot see a row announcing its entire subtree,
    // because that is a name. Evidence for SC-007, and explicitly not for SC-011.
    visit('/admin/spike')
        ->inLightMode()
        ->assertNoAccessibilityIssues();
});

it('reports zero critical accessibility issues in dark mode', function () {
    // Its own case, not chained to the light-mode one: a chain stops at the first
    // failure, so a dark-mode regression behind a light-mode failure would never
    // be seen (AGENTS.md R-023).
    visit('/admin/spike')
        ->inDarkMode()
        ->assertNoAccessibilityIssues();
});

it('applies a different rule in dark mode, proving the scheme really switched', function () {
    // ⚠️ Without this, both accessibility runs above could be executing against
    // the same light-mode page and nobody would know. `inDarkMode()` has to be
    // shown to change something the page actually computes.
    $background = visit('/admin/spike')
        ->inDarkMode()
        ->script("getComputedStyle(document.getElementById('ltree-spike-probe')).backgroundColor");

    expect($background)->toBe('rgb(37, 99, 235)');
});

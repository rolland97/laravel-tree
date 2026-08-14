<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Isolation probes for the R10 spike.
 *
 * ⚠️ These exist because the spike failed and the failure had to be attributed
 * before it could be called an answer. "The panel does not work" and "the harness
 * does not serve HTML" are very different findings, and only one of them blocks
 * US2–US4.
 */
it('serves plain HTML from the package harness at all', function () {
    Route::get('/probe-plain', fn () => '<html><head><title>plain</title></head><body><div id="p">ok</div></body></html>');

    visit('/probe-plain')->assertPresent('#p');
});

it('serves a static file from public_path, which is how compiled assets arrive', function () {
    // The mechanism FilamentAsset relies on: `filament:assets` copies registered
    // files into /public, and the harness serves /public statically.
    $path = public_path('ltree-probe.css');
    file_put_contents($path, '#p { background-color: rgb(1, 2, 3); }');

    Route::get('/probe-static', fn () => '<html><head><link rel="stylesheet" href="/ltree-probe.css"></head>'
        .'<body><div id="p">ok</div></body></html>');

    $colour = visit('/probe-static')->script("getComputedStyle(document.getElementById('p')).backgroundColor");

    @unlink($path);

    expect($colour)->toBe('rgb(1, 2, 3)');
});

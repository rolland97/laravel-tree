<?php

declare(strict_types=1);

namespace Rolland\Tree\Filament;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * The Filament bridge.
 *
 * ⚠️ Registered conditionally by TreeServiceProvider, and never referenced from
 * anything under src/ outside this directory. filament/filament is a require-dev
 * and suggest dependency only (AGENTS.md R-001).
 *
 * ⚠️ Assets are registered COMPILED and COMMITTED, from resources/dist/. A host
 * runs no bundler, edits no theme, and points no content glob into vendor/
 * (constitution Principle VI). The honest form of that claim: Filament copies
 * registered assets into /public via `php artisan filament:assets`, so a command
 * does run — but Filament's installer already wires `filament:upgrade` into
 * `post-autoload-dump`, so it fires on every `composer install` in a normal
 * Filament application. No bundler, no npm, no theme change, and no step a
 * Filament app does not already run (research R6).
 */
final class FilamentTreeServiceProvider extends ServiceProvider
{
    public const PACKAGE = 'rolland97/laravel-tree';

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'tree');

        FilamentAsset::register([
            Css::make('tree', dirname(__DIR__, 2).'/resources/dist/tree.css'),
            Js::make('tree', dirname(__DIR__, 2).'/resources/dist/tree.js'),
        ], package: self::PACKAGE);
    }
}

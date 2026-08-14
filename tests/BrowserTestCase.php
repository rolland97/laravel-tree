<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Rolland\Tree\Tests\Fixtures\Panel\TestPanelProvider;

/**
 * A Testbench application with a real Filament panel, served over HTTP.
 *
 * ⚠️ This class is the answer to research R10, which blocked US2–US4. It must
 * never be loaded by the `core` suite: `filament/filament` is uninstalled in the
 * core-without-filament CI job, and every import above would then be missing.
 * `tests/Pest.php` binds it to `Bridge` and `Browser` only.
 */
abstract class BrowserTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // ⚠️ ORDER IS LOAD-BEARING, and this cost real time to find.
        //
        // `Filament\Support\SupportServiceProvider` does
        // `$this->app->bind(DataStore::class, DataStoreOverride::class)` — a
        // NON-SHARED bind. Laravel's `bind()` unsets any existing instance, so it
        // wipes the singleton Livewire registered via `app()->instance(...)` in
        // its own `register()`. With a non-shared DataStore, every `app(DataStore)`
        // is a fresh object: `setErrorBag()` writes to one and `getErrorBag()`
        // reads from another, returns null, and every Filament page 500s with
        // `ViewErrorBag::put(): Argument #2 ($bag) must be of type MessageBag,
        // null given`.
        //
        // Real applications never see this because Composer's package manifest
        // registers `filament/support` before `livewire/livewire` alphabetically,
        // so Livewire's `instance()` lands last and wins. Testbench takes this
        // list verbatim, so the order has to be reproduced deliberately:
        // **Livewire LAST.**
        return [
            ...parent::getPackageProviders($app),
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
        $app['config']->set('view.paths', [
            dirname(__DIR__).'/resources/views',
            $app->basePath('resources/views'),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // ⚠️ Exactly what a host runs, and the reason the "no build step" claim
        // has to be stated carefully. `filament:assets` copies REGISTERED assets
        // into /public — no bundler, no npm, no theme edit, but a command does
        // run. In a normal Filament application it already fires on every
        // `composer install` through the `filament:upgrade` post-autoload-dump
        // hook (research R6).
        //
        // Without this the page links a stylesheet that 404s, and every styling
        // assertion would pass vacuously against an unstyled page.
        $this->artisan('filament:assets')->run();
    }
}

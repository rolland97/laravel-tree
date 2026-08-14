<?php

declare(strict_types=1);

namespace Rolland\Tree;

use Illuminate\Support\ServiceProvider;
use Rolland\Tree\Filament\FilamentTreeServiceProvider;

final class TreeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::packagePath('config/tree.php'), 'tree');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(self::packagePath('lang'), 'tree');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::packagePath('config/tree.php') => $this->app->configPath('tree.php'),
            ], 'tree-config');

            $this->publishes([
                self::packagePath('lang') => $this->app->langPath('vendor/tree'),
            ], 'tree-translations');

            // ⚠️ A `.stub`, published to a timestamped migration the host then edits
            // and runs. Never `loadMigrationsFrom` — the package must not run a
            // migration of its own (constitution Principle III, AGENTS.md R-002).
            $this->publishes([
                self::packagePath('database/migrations/add_tree_columns.php.stub') => $this->app->databasePath(
                    'migrations/'.date('Y_m_d_His').'_add_tree_columns.php'
                ),
            ], 'tree-migrations');
        }

        // The bridge is registered only when a Filament panel could exist. The core
        // MUST load without it (constitution Principle V, AGENTS.md R-001), and the
        // proof is the CI job that uninstalls filament/filament and runs the core
        // suite — asserting the boundary is not proving it (research R8).
        //
        // ⚠️ The class name is a STRING on purpose. Written as `\Filament\Panel::class`
        // Pint's fully_qualified_strict_types fixer rewrites it to a `use Filament\Panel;`
        // import at the top of the core's own service provider — harmless at runtime,
        // but it makes the core read as if it names Filament, and it shadowed the
        // relative `Filament\FilamentTreeServiceProvider` reference below.
        if (class_exists('Filament\Panel')) {
            $this->app->register(FilamentTreeServiceProvider::class);
        }
    }

    private static function packagePath(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}

<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Rolland\Tree\Tests\Fixtures\Page;
use Rolland\Tree\TreeServiceProvider;

/**
 * ⚠️ Nothing in this class may reference Filament, directly or through a use statement.
 *
 * The `core` suite runs in CI with `filament/filament` UNINSTALLED (AGENTS.md R-027,
 * research R8), and this base class is loaded by every suite. A Filament import here
 * would make that job fail for a reason that has nothing to do with the core.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TreeServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    /**
     * Point the package's GLOBAL configuration at the Page fixture's column names.
     *
     * ⚠️ Not a per-model override — configuration is global in v1 (data-model.md
     * § Configuration). This moves the global seam, which is precisely what needs
     * proving: every configurable name can be accidentally hard-coded to the shape
     * of a single fixture and still pass (research R9).
     */
    protected function usePageColumns(): void
    {
        config([
            'tree.parent_column' => Page::PARENT_COLUMN,
            'tree.position_column' => Page::POSITION_COLUMN,
            'tree.tiebreaker' => Page::TIEBREAKER,
        ]);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Rolland\Tree\Filament\FilamentTreeServiceProvider;
use Rolland\Tree\TreeServiceProvider;

it('merges the package configuration so a host that publishes nothing still works', function () {
    expect(config('tree.parent_column'))->toBe('parent_id')
        ->and(config('tree.position_column'))->toBe('position')
        ->and(config('tree.tiebreaker'))->toBe('name');
});

it('offers the configuration for publishing under the tree-config tag', function () {
    $paths = TreeServiceProvider::pathsToPublish(TreeServiceProvider::class, 'tree-config');

    expect($paths)->not->toBeEmpty()
        ->and(array_keys($paths)[0])->toEndWith('config/tree.php');
});

it('offers translations for publishing so no host is stuck with our words', function () {
    $paths = TreeServiceProvider::pathsToPublish(TreeServiceProvider::class, 'tree-translations');

    expect($paths)->not->toBeEmpty();
    expect(Lang::get('tree::tree.refused.unreachable_reference'))
        ->not->toBe('tree::tree.refused.unreachable_reference');
});

// ⚠️ AGENTS.md R-002 / constitution Principle III. The schema change ships as a
// `.stub`, so the host names its own table and runs it. A `.php` migration in a
// package path is auto-discovered by `loadMigrationsFrom` and by Laravel's own
// package discovery — this asserts we published the stub, and only the stub.
it('ships the schema change as a stub the host publishes, never as a live migration', function () {
    $paths = TreeServiceProvider::pathsToPublish(TreeServiceProvider::class, 'tree-migrations');

    expect($paths)->not->toBeEmpty();

    foreach (array_keys($paths) as $source) {
        expect($source)->toEndWith('.php.stub');
    }

    expect(glob(dirname(__DIR__, 2).'/database/migrations/*.php'))->toBe([]);
});

it('registers the Filament bridge if and only if a panel class exists', function () {
    // ⚠️ This assertion must hold in BOTH worlds, because this suite runs twice:
    // once with filament/filament installed, and once with it removed by the
    // core-without-filament CI job. An assertion that Filament exists would turn
    // that job red for a reason that has nothing to do with the core.
    //
    // The real proof of the boundary is that job booting the package at all —
    // asserting the boundary is not proving it (research R8).
    $loaded = app()->getLoadedProviders();

    expect($loaded)->toHaveKey(TreeServiceProvider::class);

    expect(array_key_exists(FilamentTreeServiceProvider::class, $loaded))
        ->toBe(class_exists('Filament\Panel'));
});

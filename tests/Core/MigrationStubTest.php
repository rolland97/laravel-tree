<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2 — the published migration stub must actually RUN.
 *
 * ⚠️ Until now only its *publishability* was asserted, and the fixtures use
 * migrations of their own. The stub is the very first thing every host runs, and
 * a syntax error or a bad schema call in it would have shipped with the entire
 * suite green — the same shape as C1 and C2: green because nothing exercised the
 * requirement, not because it was satisfied.
 */
function publishedStubPath(): ?string
{
    $found = glob(database_path('migrations/*_add_tree_columns.php'));

    return $found === false || $found === [] ? null : $found[0];
}

function forgetPublishedStub(): void
{
    $path = publishedStubPath();

    if ($path !== null) {
        @unlink($path);
    }

    Schema::dropIfExists('stub_targets');
}

// ⚠️ Before AND after. A stub left behind by a failed run would be picked up as a
// real migration by every later test in this process, and the failure would land
// somewhere unrelated to the cause.
beforeEach(fn () => forgetPublishedStub());
afterEach(fn () => forgetPublishedStub());

it('publishes a stub that creates the columns the package needs', function () {
    $this->artisan('vendor:publish', ['--tag' => 'tree-migrations', '--force' => true])->run();

    $path = publishedStubPath();
    expect($path)->not->toBeNull('the stub did not publish at all');

    // The host's job, and the reason it ships as a stub: name your own table.
    file_put_contents($path, str_replace(
        "'YOUR_TABLE'",
        "'stub_targets'",
        (string) file_get_contents($path)
    ));

    Schema::create('stub_targets', function (Blueprint $table): void {
        $table->id();
    });

    $migration = require $path;
    $migration->up();

    // ⚠️ Read from CONFIG, not hard-coded. This is what proves the stub and
    // config/tree.php agree: a stub creating `parent` while the package reads
    // `parent_id` would leave every host with a tree that cannot find its rows.
    expect(Schema::hasColumn('stub_targets', config('tree.parent_column')))->toBeTrue();
    expect(Schema::hasColumn('stub_targets', config('tree.position_column')))->toBeTrue();
});

it('publishes a stub whose down() reverses it', function () {
    // A migration a host cannot roll back is a migration they cannot trial.
    $this->artisan('vendor:publish', ['--tag' => 'tree-migrations', '--force' => true])->run();

    $path = publishedStubPath();
    expect($path)->not->toBeNull();

    file_put_contents($path, str_replace(
        "'YOUR_TABLE'",
        "'stub_targets'",
        (string) file_get_contents($path)
    ));

    Schema::create('stub_targets', function (Blueprint $table): void {
        $table->id();
    });

    $migration = require $path;
    $migration->up();
    $migration->down();

    expect(Schema::hasColumn('stub_targets', config('tree.position_column')))->toBeFalse();
    expect(Schema::hasColumn('stub_targets', config('tree.parent_column')))->toBeFalse();
});

it('keeps the table name a PLACEHOLDER in the shipped stub', function () {
    // ⚠️ Guards the direction this could rot: someone runs the test above, finds
    // it convenient to hard-code a table name, and ships a stub that silently
    // migrates the wrong table for every host.
    $shipped = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/add_tree_columns.php.stub');

    expect($shipped)->toContain("'YOUR_TABLE'");
});

/*
 * ⚠️ REMOVED: a textual guard asserting the stub "contains 'position'".
 *
 * A mutation renaming the created column to `positionx` left it GREEN, because
 * the stub's `index(['parent_id', 'position'])` line still contained the string.
 * It looked like stub-versus-config coverage and was really a substring search.
 *
 * The two execution cases above now read the column names from config and assert
 * against the REAL schema, which is the claim that was wanted all along.
 */

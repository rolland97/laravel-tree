<?php

declare(strict_types=1);

use Filament\Pages\BasePage;
use Filament\Pages\Page as PanelPage;
use Filament\Resources\Pages\Page as ResourcePage;
use Rolland\Tree\Filament\Concerns\InteractsWithTree;
use Rolland\Tree\Filament\Pages\TreePage;
use Rolland\Tree\Tests\Fixtures\Panel\CategoryTreePage;
use Rolland\Tree\Tests\Fixtures\Panel\TreeCategories;

/**
 * PA-1's composition rules, enforced instead of remembered.
 *
 * ⚠️ **This file exists because CI caught what a local probe got wrong.** While
 * extracting the trait, the `$model` collision was probed directly and the probe
 * also reported that a trait property may differ from a **parent's** property of
 * the same name. On **PHP 8.5 that is true. On 8.3 and 8.4 it is a fatal error**,
 * and the package supports all three:
 *
 * ```
 * Filament\Pages\Page and Rolland\Tree\Filament\Concerns\InteractsWithTree
 * define the same property ($view) in the composition of
 * Rolland\Tree\Filament\Pages\TreePage. However, the definition differs and
 * is considered incompatible.
 * ```
 *
 * Five of ten CI jobs died at COMPILE time on that one line. A probe run on one
 * PHP version was written down as a language rule — the F15/F16 lesson in this
 * repository's validation log, repeated: a claim about behaviour nothing had
 * actually exercised.
 *
 * ⚠️ The guard below is therefore deliberately GENERAL. It does not check `$view`;
 * it checks that the trait declares no property that any Filament page ancestor
 * also declares, which is the rule the defect broke. A future property added to
 * the trait is caught here on **any** PHP version, rather than on two thirds of
 * the matrix.
 */

/**
 * Every property name declared anywhere up the Filament page hierarchy.
 *
 * @return array<string, string> property name => declaring class
 */
function filamentPageProperties(): array
{
    $found = [];

    foreach ([BasePage::class, PanelPage::class, ResourcePage::class] as $class) {
        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            $found[$property->getName()] ??= $property->getDeclaringClass()->getName();
        }
    }

    return $found;
}

it('declares no trait property that a Filament page ancestor also declares', function () {
    // ⚠️ A collision here is a FATAL COMPILE ERROR on PHP 8.3 and 8.4 — not a
    // deprecation, not a warning, and not something any assertion inside the
    // composed class could ever run to observe. The whole file fails to load.
    $traitProperties = array_map(
        fn (ReflectionProperty $p): string => $p->getName(),
        (new ReflectionClass(InteractsWithTree::class))->getProperties()
    );

    $collisions = array_intersect($traitProperties, array_keys(filamentPageProperties()));

    expect($collisions)->toBe([], 'trait property collides with a Filament page property: '.implode(', ', $collisions));
});

it('reaches the tree view through getView(), which is a method and composes safely', function () {
    // ⚠️ The supported seam. `BasePage::render()` calls `view($this->getView())`,
    // and a trait METHOD beats an INHERITED one on every supported PHP version —
    // which is the half of the original probe that was correct.
    expect((new ReflectionMethod(InteractsWithTree::class, 'getView'))->isPublic())->toBeTrue();
});

it('renders the package view from a panel-page host', function () {
    expect(Livewire\Livewire::test(CategoryTreePage::class)->instance()->getView())
        ->toBe('tree::tree');
});

it('renders the package view from a resource-page host', function () {
    // Both hosts, because the entire point of PA-1 is that they share one body —
    // and the property collision broke the panel-page host, not the trait user.
    expect(Livewire\Livewire::test(TreeCategories::class)->instance()->getView())
        ->toBe('tree::tree');
});

it('keeps TreePage composable at all', function () {
    // A composition fatal cannot be caught, so the only honest assertion is that
    // the class resolves. If the trait ever collides again this whole FILE dies at
    // load — which is itself the signal, on any PHP version.
    expect(class_exists(TreePage::class))->toBeTrue();
    expect(class_exists(CategoryTreePage::class))->toBeTrue();
});

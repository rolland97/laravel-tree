<?php

declare(strict_types=1);

use Rolland\Tree\Exceptions\CycleException;
use Rolland\Tree\Exceptions\InvalidTargetException;
use Rolland\Tree\Exceptions\UnreachableReferenceException;

/**
 * E4 — spec FR-019: *"All copy the package emits MUST be overridable by the host
 * without modifying the package."*
 *
 * ⚠️ The existing coverage asserted that translations RESOLVE and that the lang
 * files are PUBLISHABLE. Neither is the requirement. The requirement is that a
 * host's override actually **wins**, and nothing tested that.
 */
it('lets a host override a refusal message', function () {
    app('translator')->addLines(['tree.refused.cycle' => 'HOST WORDING'], 'en', 'tree');

    expect(CycleException::make()->getMessage())->toBe('HOST WORDING');
});

it('lets a host override every refusal, not merely the first', function () {
    app('translator')->addLines([
        'tree.refused.invalid_target' => 'HOST TARGET WORDING',
        'tree.refused.unreachable_reference' => 'HOST REFERENCE WORDING',
    ], 'en', 'tree');

    expect(InvalidTargetException::make()->getMessage())->toBe('HOST TARGET WORDING');
    expect(UnreachableReferenceException::make()->getMessage())->toBe('HOST REFERENCE WORDING');
});

it('lets a host override the keyboard announcements', function () {
    // ⚠️ These are the strings a screen-reader user HEARS. A package that hard-coded
    // them would be untranslatable in exactly the place it matters most.
    app('translator')->addLines(['tree.announce.picked_up' => 'HOST PICKED UP :name'], 'en', 'tree');

    expect((string) __('tree::tree.announce.picked_up', ['name' => 'Delta']))
        ->toBe('HOST PICKED UP Delta');
});

it('keeps the package wording when the host overrides nothing', function () {
    // ⚠️ The other half, and the one that stops the guard passing vacuously: if
    // the package emitted an empty string the override tests above would still
    // pass, because an override would still win over nothing.
    expect(CycleException::make()->getMessage())
        ->toBe('A node cannot be moved inside itself or one of its own descendants.');
});

it('resolves a placeholder rather than emitting it literally', function () {
    // ⚠️ A sibling slice in the source application mangled a placeholder in a way
    // that still RENDERED correctly, so every assertion passed and only a guard
    // reading the translation files caught it (research R7).
    $announcement = (string) __('tree::tree.announce.moved', [
        'name' => 'Delta',
        'position' => 2,
        'total' => 5,
    ]);

    expect($announcement)->toContain('Delta')
        ->and($announcement)->toContain('2')
        ->and($announcement)->toContain('5')
        ->and($announcement)->not->toContain(':name')
        ->and($announcement)->not->toContain(':position')
        ->and($announcement)->not->toContain(':total');
});

it('leaves no announcement key holding an unresolvable placeholder', function () {
    // Every template the controller substitutes into, checked as a set — a new
    // announcement added with a typo'd placeholder would slip past a spot check.
    $announcements = trans('tree::tree.announce');

    expect($announcements)->toBeArray();

    $known = [':name', ':position', ':total', ':parent'];

    foreach ($announcements as $key => $template) {
        preg_match_all('/:[a-z_]+/', (string) $template, $matches);

        foreach ($matches[0] as $placeholder) {
            // ⚠️ `in_array` inside `toBeTrue`, NOT `toContain($placeholder, $message)`.
            // Pest's toContain() is VARIADIC over expected values, so a message
            // passed there becomes a second value the array must contain — the
            // assertion then fails for a reason that has nothing to do with the
            // check, which is exactly what it did when first written.
            expect(in_array($placeholder, $known, true))->toBeTrue(
                "tree.announce.{$key} uses {$placeholder}, which the controller never substitutes"
            );
        }
    }
});

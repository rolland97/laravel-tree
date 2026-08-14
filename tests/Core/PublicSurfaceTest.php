<?php

declare(strict_types=1);

use Rolland\Tree\Actions\MoveNode;
use Rolland\Tree\Actions\PlaceNode;
use Rolland\Tree\Actions\ReorderSiblings;
use Rolland\Tree\Actions\ResolveSiblingPlacement;
use Rolland\Tree\Enums\SiblingPlacement;

/**
 * T040 / T041 — the two guards that keep the reason this package exists enforced
 * rather than commented.
 *
 * Raised by the post-design constitution re-check (plan.md): `MoveNode` takes an
 * integer position, and today only a doc comment separates it from `PlaceNode`.
 * A doc comment is weak enforcement for the package's most important rule.
 */

/**
 * @return list<ReflectionMethod>
 */
function publicEntryPoints(): array
{
    $methods = [];

    foreach ([PlaceNode::class, ResolveSiblingPlacement::class, ReorderSiblings::class, MoveNode::class] as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isConstructor()) {
                $methods[] = $method;
            }
        }
    }

    return $methods;
}

// ── T040 — no index-taking entry point ───────────────────────────────────────

it('offers exactly one integer-position parameter in the whole public surface, on MoveNode', function () {
    // ⚠️ Constitution Principle II / AGENTS.md R-007. A host calling MoveNode
    // directly with a hand-computed index is the ONE way to reintroduce the 071
    // defect. Every other entry point must be impossible to call that way.
    $found = [];

    foreach (publicEntryPoints() as $method) {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === 'int') {
                $found[] = $method->class.'::'.$method->getName().'($'.$parameter->getName().')';
            }
        }
    }

    expect($found)->toBe([MoveNode::class.'::handle($position)']);
});

it('keeps PlaceNode — the entry point hosts are told to call — free of any integer', function () {
    foreach ((new ReflectionMethod(PlaceNode::class, 'handle'))->getParameters() as $parameter) {
        $type = $parameter->getType();

        expect($type instanceof ReflectionNamedType && $type->getName() === 'int')->toBeFalse(
            "PlaceNode::handle(\${$parameter->getName()}) takes an int — that is the defect this package exists to prevent"
        );
    }
});

it('says in MoveNode\'s own docblock where its integer comes from', function () {
    // ⚠️ The distinction between "an index the package produced" and "an index the
    // client sent" cannot be expressed in a type. It is stated here, and this
    // assertion is what stops the statement being quietly deleted.
    $doc = (string) (new ReflectionMethod(MoveNode::class, 'handle'))->getDocComment();

    expect($doc)->toContain('ResolveSiblingPlacement');
    expect(strtolower($doc))->toContain('not');
});

it('offers no placement case that names a position instead of a neighbour', function () {
    foreach (SiblingPlacement::cases() as $case) {
        expect($case->name)->not->toContain('Index');
        expect($case->name)->not->toContain('At');
    }
});

it('exposes no method whose name advertises an index', function () {
    foreach (publicEntryPoints() as $method) {
        $name = strtolower($method->getName());

        expect($name)->not->toContain('index');
        expect($name)->not->toContain('atposition');
    }
});

// ── T041 — $renderedSiblingIds must have NO default ──────────────────────────

it('gives renderedSiblingIds no default value anywhere in the surface', function () {
    // ⚠️ An empty default would silently turn the visibility refusal into a no-op
    // for every caller who omitted it — a security check quietly becoming nothing.
    // A caller that genuinely renders everything passes everything.
    $checked = 0;

    foreach (publicEntryPoints() as $method) {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() !== 'renderedSiblingIds') {
                continue;
            }

            $checked++;

            expect($parameter->isOptional())->toBeFalse(
                $method->class.'::'.$method->getName().' makes $renderedSiblingIds optional'
            );
            expect($parameter->isDefaultValueAvailable())->toBeFalse(
                $method->class.'::'.$method->getName().' gives $renderedSiblingIds a default'
            );
        }
    }

    // If the parameter is ever renamed away, this test must not silently pass by
    // checking nothing.
    expect($checked)->toBe(2, 'expected $renderedSiblingIds on both PlaceNode::handle and ResolveSiblingPlacement::handle');
});

it('names the two sibling lists differently so they cannot be conflated', function () {
    // data-model.md § The two sibling lists: conflating the complete group with
    // the rendered subset IS the defect. One is a parameter; the other is queried.
    $names = array_map(
        fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(PlaceNode::class, 'handle'))->getParameters()
    );

    expect($names)->toContain('renderedSiblingIds');
    expect($names)->not->toContain('siblingIds');
    expect($names)->not->toContain('siblings');
});

<?php

declare(strict_types=1);

namespace Rolland\Tree\Exceptions;

use DomainException;

/**
 * The host answered `false` to `isValidTreeTarget()`.
 *
 * The package has no opinion about what makes a target valid — this exception
 * reports the host's own answer back to the host.
 */
final class InvalidTargetException extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('tree::tree.refused.invalid_target'));
    }
}

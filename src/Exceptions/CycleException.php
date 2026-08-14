<?php

declare(strict_types=1);

namespace Rolland\Tree\Exceptions;

use DomainException;

/**
 * The destination is the node itself, or one of its own descendants.
 */
final class CycleException extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('tree::tree.refused.cycle'));
    }
}

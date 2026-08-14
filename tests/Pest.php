<?php

declare(strict_types=1);

use Rolland\Tree\Tests\BrowserTestCase;
use Rolland\Tree\Tests\TestCase;

// ⚠️ `Core` gets the Filament-free base class, and nothing else does.
//
// The core suite runs in CI with filament/filament UNINSTALLED (AGENTS.md R-027),
// so binding BrowserTestCase — which imports a dozen Filament providers — anywhere
// near it would break that job for a reason that has nothing to do with the core.
uses(TestCase::class)->in('Core');
uses(BrowserTestCase::class)->in('Bridge', 'Browser');

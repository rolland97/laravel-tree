<?php

declare(strict_types=1);

namespace Rolland\Tree\Tests\Fixtures\Panel;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The smallest panel that can be served to a browser from inside a PACKAGE.
 *
 * ⚠️ This exists to answer research R10, which was `[open]` and blocked three of
 * the four user stories: Testbench can boot a panel in-process, but whether it can
 * SERVE one to a browser driver — and whether that panel serves COMPILED CSS —
 * had not been verified. Discovering the harness cannot do it after the tree page
 * exists converts a spike into a rewrite (AGENTS.md R-039).
 *
 * ⚠️ No custom theme, deliberately. SC-005 is about a themeless panel: if the
 * package's styling only works alongside a host's own theme, it does not work.
 */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('admin')
            ->colors(['primary' => '#2563eb'])
            // ⚠️ Registered HERE, not in a test's beforeEach. Filament builds a
            // panel's routes while booting, so a page added afterwards resolves to
            // a 404 — and a 404 page passes `assertNoAccessibilityIssues()`
            // vacuously, which is how this nearly read as a green spike.
            ->pages([SpikePage::class])
            // ⚠️ No `AuthenticateSession` and no `->login()`. This panel has no
            // guard configured, and the spike's question is about serving and
            // styling, not about authentication — a panel that 500s inside the
            // auth stack would answer nothing.
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ConvertEmptyStringsToNull::class,
            ]);
    }
}

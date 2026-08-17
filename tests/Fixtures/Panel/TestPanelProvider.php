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
use Illuminate\Support\Facades\Event;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rolland\Tree\Events\NodeMoved;
use Rolland\Tree\Events\SiblingsReordered;
use Rolland\Tree\Tests\Fixtures\MoveCounter;

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
    public function boot(): void
    {
        // ⚠️ No parent::boot() — Filament\PanelProvider does not declare one.

        // Registered HERE, in the SERVED app's container, so browser tests can
        // count events raised by a real HTTP request. See MoveCounter.
        Event::listen(NodeMoved::class, function (): void {
            MoveCounter::$moved++;
        });

        Event::listen(SiblingsReordered::class, function (): void {
            MoveCounter::$reordered++;
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('admin')
            // ⚠️ Filament's DEFAULT palette, not a hand-picked hex.
            //
            // A custom `primary` of #2563eb made Filament render its own buttons as
            // white on #477ae3 — contrast 4.06 against a required 4.5, which axe
            // reported against the tree page. The buttons are Filament's, the
            // colour was this fixture's, and the package's job is to INHERIT the
            // host's accent rather than define a palette (AGENTS.md R-021). So the
            // fixture stops choosing a bad one; the package is not "fixed" by
            // overriding a host's colours, which is the one thing it must not do.
            //
            // Worth keeping in mind for hosts: laravel-tree cannot rescue a panel
            // whose own primary fails contrast.
            // ⚠️ Registered HERE, not in a test's beforeEach. Filament builds a
            // panel's routes while booting, so a page added afterwards resolves to
            // a 404 — and a 404 page passes `assertNoAccessibilityIssues()`
            // vacuously, which is how this nearly read as a green spike.
            ->pages([SpikePage::class, CategoryTreePage::class, RecordingTreePage::class, AnnouncementTreePage::class])
            // ⚠️ The PA-1 host: a tree on a RESOURCE INDEX page. Registered here
            // for the same reason the pages are — Filament builds a panel's routes
            // while booting, so anything added afterwards resolves to a 404.
            ->resources([CategoryResource::class])
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

# Phase 0 Research — laravel-tree v1

Decisions taken before design, each with the alternatives that were rejected and why. A
decision recorded here without its rejected alternative is not a decision, it is a preference.

⚠️ **Status of the evidence.** Items marked **[verified]** were checked against documentation
or running source during this phase. Items marked **[carried]** are inherited from the source
application, where they were established by a live defect — they are not re-derived here, and
their evidence is cited. Items marked **[open]** are genuinely unresolved and name what would
settle them.

---

## R1 — How a move is expressed **[carried]**

**Decision**: A reference sibling plus `SiblingPlacement` (`Before`, `After`, `LastChild`).
No public entry point accepts a numeric index.

**Rejected**: a positional index, which is what the source application used until 071.

**Why**: The browser cannot count rows it never drew. Privacy scoping removes rows from the
query and quick-search removes them from the DOM, so an index counted client-side resolves
server-side against a different list. Two live defects followed: same-parent reorder renumbered
a partial list and left the rest holding stale, colliding positions; cross-parent move
renumbered the right list at the wrong index. It is also an authorization hole — an index lets a
tampered payload address a node privacy hides, whereas a reference must be proven visible.

**Evidence**: the consumer's keyboard-order slice, an internal merge request.

---

## R2 — Where the visibility boundary sits **[carried]**

**Decision**: The caller passes the ids it rendered; the package resolves against the complete
group. Two parameters, deliberately not one.

**Rejected**: the package applying the host's scope itself (it cannot know it), and the package
resolving against the rendered list (that is the defect in R1).

**Why**: The caller owns the privacy scope and the search filter; the package owns the ordering
rule. Splitting them keeps exactly one copy of each. A reference that is in the complete group
but *not* in the rendered list must be refused, because the actor cannot have aimed at a row
they were never shown.

---

## R3 — Tie-breaking a group's read order **[verified]**

**Decision**: Order by the position column, then by a configurable tiebreaker column
(`config('tree.tiebreaker')`, default `name` where present, else the key).

**Rejected**: ordering by position alone.

**Why**: Positions in real data are neither unique nor dense — the source application's own
`completeGroup()` orders by `position` then `name` precisely because legacy rows collide. If the
package ordered by position alone, its resolved index would mean something different from what
the user saw.

⚠️ **This has a testing consequence that must not be lost**: because the read tie-breaks by
name, a wrong implementation and a right one can produce the **same visible order** for a
carelessly-named fixture. Two of fourteen guards in the source application passed against the
defect for exactly this reason. Fixture naming is a correctness concern here, not cosmetics
(spec FR-048, `AGENTS.md` R-025).

---

## R4 — Strict comparison of keys **[verified]**

**Decision**: Cast plucked keys to `int` before comparing, and compare strictly.

**Rejected**: loose comparison.

**Why**: `pluck()->all()` is typed `array<mixed>`, and a key arriving from the driver as a
numeric string would silently never match its own group under a strict comparison — the move
would be refused as "reference not in group" for a perfectly valid reference. Found by static
analysis in the source application, not by a test.

**Evidence**: `ResolveSiblingPlacement::completeGroup()` in consumer carries this cast with a
comment saying why.

---

## R5 — How the host learns a node moved **[verified]**

**Decision**: Fire `NodeMoved` and `SiblingsReordered`. The package writes no log.

**Rejected**: calling an activity-log helper directly, as the source application does inline in
`MoveVendorCategory`.

**Why**: It is the single largest decoupling win in the extraction — it removes
`spatie/laravel-activitylog` from the dependency set entirely. The host already has an audit
trail if it wants one, and already knows who its actor is; the package would have to guess both.

⚠️ **Consequence for the adopt slice**: the source application must add a listener that
recreates its existing audit entry, and its existing audit tests must pass **unchanged**. That
is the strongest available signal that this seam held.

---

## R6 — Shipping CSS and JavaScript **[verified]**

**Decision**: Compile to `resources/dist/`, commit the output, register through
`FilamentAsset::register([...], package: 'rolland97/laravel-tree')` in the bridge provider's
boot. Use `Css::make()` for the stylesheet and `AlpineComponent::make()` for the controller.

**Rejected**:

1. **Shipping blades with host utility classes plus an `@source` glob into `vendor/`.** A
   Filament panel compiles only the utilities its own CSS references, so the blade renders
   unstyled and the failure is *silent* — it reads as a bug in the host's own CSS. It also makes
   the package unusable to a host that does not use that CSS framework at all.
2. **Vite.** Filament's own guidance is that `filament:assets` copies files as-is without
   resolving imports, so a file using `import` must be bundled first; for Alpine components it
   recommends esbuild specifically.

⚠️ **A claim in the constitution needed correcting here, and this is the entry that corrects
it.** Principle VI says a host installs the package "without running a bundler, editing a theme,
or adding a build step". Registered assets are copied into `/public` by
`php artisan filament:assets`, so a command **does** run. It is not a new burden — Filament's
installer wires `filament:upgrade` into `post-autoload-dump`, so it fires on every
`composer install` in a normal Filament application — but the honest claim is **"no bundler, no
npm, no theme change, and no step a Filament app does not already run"**. The overclaim was
caught by reading Filament's asset documentation rather than by assuming the principle was
already true.

---

## R7 — How the Alpine controller receives its translated strings **[verified]**

**Decision**: Pass them as the argument to the `x-data` factory —
`x-data="ltree(@js($this->treeStrings()))"`.

**Rejected**: a `$wire` call (a round trip for strings the server already has, and one more
layer of string handling), and a JSON blob in a data attribute (attribute escaping, and the
values are already going through Blade).

**Why**: It is the direct translation of the source application's existing
`@js(__('...'))` construction, so translation stays server-side and host-controlled. Fewer
string-handling layers is a correctness argument here, not tidiness: a sibling slice mangled a
translation placeholder (`:daysd`) in a way that still *rendered* correctly, so every assertion
passed and only a guard reading the translation files caught it.

---

## R8 — Proving the core does not need Filament **[verified]**

**Decision**: A dedicated CI job removes `filament/filament` and runs the core suite.

**Rejected**: asserting the boundary with an architecture test over namespaces.

**Why**: An arch test proves nothing was *imported*; it does not prove the package boots. A
conditional service-provider registration can still fail on a container binding, a facade, or a
config default that only exists when Filament is installed. Removing the package is the only
check that exercises the real condition.

---

## R9 — Two models in the test suite **[verified]**

**Decision**: The fixtures are a `Category` (mirroring the source) **and** an unrelated `Page`.

**Rejected**: one fixture model.

**Why**: One consumer proves nothing — every configurable seam (column names, tiebreaker,
validity predicate) can be accidentally hard-coded to the shape of a single fixture and still
pass. The second model is what makes "generic" a measured claim.

---

## R10 — Browser testing inside a package **[verified]**

**Settled 2026-08-14 by the T043–T047 spike**, before any of US2–US4 was built on it. All four
questions are answered **yes**. `tests/Browser/SpikeTest.php` and
`tests/Browser/HarnessProbeTest.php` are the evidence and stay in the suite.

| Question | Answer | How it was shown |
|---|---|---|
| Can a **package** serve a Filament panel to a browser driver? | **yes** | `visit('/admin/spike')` renders the panel; 8/8 browser tests green |
| Does that panel serve **compiled CSS**? | **yes** | `getComputedStyle(...).backgroundColor` reads `rgb(185, 28, 28)` from `resources/dist/tree.css` |
| Can **axe** be run against it, in **both** schemes? | **yes** | `assertNoAccessibilityIssues()` under `inLightMode()` and `inDarkMode()` |
| Does the scheme switch actually take effect? | **yes** | the same probe computes `rgb(37, 99, 235)` in dark |

**The stack**: `pestphp/pest-plugin-browser` v4.3.1 over Playwright 1.62.1 (chromium 1234), on
Orchestra Testbench 11.2 with Filament 5.7.6, Livewire 4.4, Laravel 13.25, PHP 8.5.4.
`Pest\Browser\Drivers\LaravelHttpServer` boots the Testbench kernel over an amphp socket and
serves `public_path()` statically, which is exactly the path `php artisan filament:assets` copies
registered package assets into.

### ⚠️ Three traps found while settling it, all of which cost time

1. **Provider order is load-bearing, and getting it wrong looks like a Livewire bug.**
   `Filament\Support\SupportServiceProvider` runs
   `$this->app->bind(DataStore::class, DataStoreOverride::class)` — a **non-shared** bind, and
   Laravel's `bind()` unsets any existing instance. That wipes the singleton Livewire registered
   with `app()->instance(...)`, so every `app(DataStore::class)` returns a **fresh** object:
   `setErrorBag()` writes to one and `getErrorBag()` reads from another, returns `null`, and
   every Filament page 500s with
   `ViewErrorBag::put(): Argument #2 ($bag) must be of type MessageBag, null given`.
   Real applications never see this, because Composer's package manifest registers
   `filament/support` before `livewire/livewire` alphabetically and Livewire's `instance()`
   lands last. **Testbench takes `getPackageProviders()` verbatim, so Livewire must be listed
   LAST.** Recorded in `tests/BrowserTestCase.php` beside the list itself.

2. **A page registered after boot 404s — and a 404 passes every accessibility assertion.**
   Filament builds a panel's routes while booting, so `->pages([...])` in a test's `beforeEach`
   is too late. Both axe assertions went green against the error page. A dedicated
   `document.title` assertion now guards it. ⚠️ **A vacuous green is worse than a red**, and this
   is the second time this project has met the shape (see R6's silent unstyled blade).

3. **The axe pass is not decorative — it failed first, on real markup.** The spike probe was
   `#09090b` on `#dc2626`, contrast **4.11** against a required 4.5. The colours were corrected
   rather than the assertion relaxed. This is worth recording because it is the evidence that
   `assertNoAccessibilityIssues()` in this harness is actually inspecting the served page.

⚠️ **What this still does NOT settle.** Axe proves a name *exists*; it cannot see a row
announcing its entire subtree, because that is a name. SC-011 remains unproved and is not
inferable from anything above (`AGENTS.md` R-018).

---

## R11 — Two browser-test traps to carry across **[carried]**

Not decisions, but they will cost time again if forgotten:

1. **`keys($selector, ...)` is focus-then-type.** The keystroke goes to whatever is focused, so
   pressing a key mid-expand lands it on the *previously* focused row — which reads as a product
   bug. Follow every expand with an assertion that waits.
2. **`$wire.$refresh()` returns a promise that never resolves** when awaited inside a page
   evaluation. The source application's run hung for fifteen minutes. Void it.

**Evidence**: the consumer's working log § What US3 cost, finding 7.

---

## R12 — What `staudenmeir/laravel-adjacency-list` is used for **[verified]**

**Decision**: Depend on it for recursive relationships only — the descendant check that powers
the cycle guard.

**Rejected**: writing the recursive CTE by hand.

**Why**: The cycle guard is the one place the package must ask a genuinely recursive question
(`is this destination inside my own subtree?`), and the source application already answers it
with `descendantsAndSelf()`. Hand-rolling it would mean owning per-driver SQL for no gain.

⚠️ **Watch the scope of this dependency.** It is required by the *core*, so it is a real
constraint on every consumer, unlike Filament. If a future version makes it optional, that is a
`MINOR` change worth having.

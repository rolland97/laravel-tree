<?php

declare(strict_types=1);

use Rolland\Tree\Tests\Fixtures\Panel\SpikePage;

/**
 * Splits "the browser cannot reach it" from "the page does not render".
 *
 * If this fails too, the R10 blocker is not about serving to a browser at all.
 */
it('renders the panel page through the HTTP kernel', function () {
    $this->get('/admin/spike')->assertOk();
});

it('renders the page as a Livewire component', function () {
    Livewire\Livewire::test(SpikePage::class)->assertOk();
});

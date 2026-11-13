<?php

it('loads the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('foodloop')
        ->assertSee('Prototype');
});

it('says clearly that payments are simulated and data is demo data', function () {
    $this->get('/')
        ->assertSee('Payments are simulated')
        ->assertSee('demo data');
});

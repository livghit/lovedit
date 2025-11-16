<?php

use Laravel\Fortify\Features;

it('renders the landing page with expected props', function () {
    config()->set('fortify.features', [
        Features::registration(),
        Features::resetPasswords(),
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
        $page->component('welcome')
            ->has('canRegister');
    });
});

it('shows dashboard link when authenticated', function () {
    $user = App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('welcome'));
});

<?php

use App\Models\User;

test('guests are redirected to login from the application shell and settings', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/dashboard', '/settings']);

test('the authenticated application shell exposes the primary navigation', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('My Work')
        ->assertSee('Projects')
        ->assertSee('Analytics')
        ->assertSee('Activity')
        ->assertSee('Settings');
});

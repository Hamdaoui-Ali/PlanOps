<?php

use App\Models\User;

it('renders a PlanOps landing page without Laravel starter content', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('PLANOPS')
        ->assertSee('Track the work.')
        ->assertSee('See the progress.')
        ->assertSee('Get Started')
        ->assertSee('Log In')
        ->assertSee('Operational clarity')
        ->assertSee('dashboard-preview-layout', escape: false)
        ->assertSee('dashboard-preview-board', escape: false)
        ->assertSee('BLOCKED')
        ->assertSee('OVERALL PROGRESS')
        ->assertDontSee('Laravel')
        ->assertDontSee('Documentation')
        ->assertDontSee('Laracasts');
});

it('renders branded login and registration surfaces with accessible auth controls', function (): void {
    $this->get('/login')->assertOk()
        ->assertSee('PLANOPS')
        ->assertSee('Welcome back')
        ->assertSee('Remember me')
        ->assertSee('Forgot your password?')
        ->assertSee('Create an account')
        ->assertDontSee('Laravel');

    $this->get('/register')->assertOk()
        ->assertSee('PLANOPS')
        ->assertSee('Create your PlanOps account')
        ->assertSee('Already have an account?')
        ->assertSee('name="password_confirmation"', escape: false)
        ->assertDontSee('Laravel');
});

it('keeps login validation and authentication behavior intact', function (): void {
    $response = $this->from('/login')->post('/login', [
        'email' => 'not-an-email',
        'password' => '',
    ]);

    $response->assertRedirect('/login')->assertSessionHasErrors(['email', 'password']);

    $user = User::factory()->create();
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered_when_enabled()
    {
        // Enable registration for this test
        Config::set('app.enable_registration', true);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_redirects_when_disabled()
    {
        // Disable registration for this test
        Config::set('app.enable_registration', false);

        $response = $this->get('/register');

        $response->assertRedirect('/login');
    }

    public function test_new_users_can_register_when_enabled()
    {
        // Enable registration for this test
        Config::set('app.enable_registration', true);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_is_forbidden_when_disabled()
    {
        // Disable registration for this test
        Config::set('app.enable_registration', false);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/login');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_root_route_redirects_guests_to_login()
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_root_route_redirects_authenticated_users_to_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/')->assertRedirect('/dashboard');
    }
}

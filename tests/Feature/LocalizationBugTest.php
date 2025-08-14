<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LocalizationBugTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_preference_persists_after_logout_login_cycle()
    {
        // Create a user with English locale preference
        $user = User::factory()->create(['locale' => 'en']);

        // Simulate the browser sending Chinese as preferred language
        $browserHeaders = ['Accept-Language' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6'];

        // First login - should use user preference (en), not browser language (zh-CN)
        $response1 = $this->actingAs($user)
            ->withHeaders($browserHeaders)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals('en', App::getLocale(), 'First login should use user preference (en)');

        // Logout
        $this->postWithCsrf('/logout');
        $this->assertGuest();

        // Login again with same browser headers
        $loginResponse = $this->withHeaders($browserHeaders)
            ->postWithCsrf('/login', [
                'email' => $user->email,
                'password' => 'password', // Default password from factory
            ]);

        $loginResponse->assertRedirect('/dashboard');

        // Navigate to dashboard after login - should still use user preference (en)
        $response2 = $this->actingAs($user)
            ->withHeaders($browserHeaders)
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals('en', App::getLocale(), 'After logout/login cycle should still use user preference (en), not browser language (zh-CN)');
    }

    public function test_session_regeneration_preserves_user_locale()
    {
        // Create a user with English locale preference
        $user = User::factory()->create(['locale' => 'en']);

        // Simulate the browser sending Chinese as preferred language
        $browserHeaders = ['Accept-Language' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6'];

        // Login and check initial locale
        $this->actingAs($user)
            ->withHeaders($browserHeaders)
            ->get('/dashboard');

        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Session::get('locale'), 'Session should store user locale');

        // Simulate session regeneration (like what happens during login)
        Session::regenerate();

        // Make another request - should still use user preference
        $response = $this->actingAs($user)
            ->withHeaders($browserHeaders)
            ->get('/dashboard');

        $response->assertOk();
        $this->assertEquals('en', App::getLocale(), 'After session regeneration should still use user preference');
    }

    public function test_middleware_priority_with_authenticated_user()
    {
        // Create a user with English locale preference
        $user = User::factory()->create(['locale' => 'en']);

        // Set a different locale in session
        Session::put('locale', 'fr');

        // Browser prefers Chinese
        $browserHeaders = ['Accept-Language' => 'zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7,zh-CN;q=0.6'];

        // With authenticated user, user preference should take priority over session
        $response = $this->actingAs($user)
            ->withHeaders($browserHeaders)
            ->get('/dashboard');

        $response->assertOk();
        $this->assertEquals('en', App::getLocale(), 'User preference should override session locale');
        $this->assertEquals('en', Session::get('locale'), 'Session should be updated with user preference');
    }
}

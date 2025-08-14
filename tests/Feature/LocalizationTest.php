<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_preference_takes_priority_over_browser_language()
    {
        // Create a user with Chinese locale preference
        $user = User::factory()->create(['locale' => 'zh-CN']);

        // Make a request with English browser language but authenticated user
        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/dashboard');

        $response->assertOk();
        
        // The application should use the user's preference (zh-CN), not browser language (en)
        $this->assertEquals('zh-CN', App::getLocale());
    }

    public function test_session_locale_takes_priority_over_browser_language()
    {
        // Set session locale to French
        Session::put('locale', 'fr');

        // Make a request with English browser language to a public route
        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/login');

        $response->assertOk();

        // The application should use session locale (fr), not browser language (en)
        $this->assertEquals('fr', App::getLocale());
    }

    public function test_url_parameter_updates_user_preference_and_session()
    {
        // Create a user with English locale
        $user = User::factory()->create(['locale' => 'en']);

        // Make a request with locale parameter
        $response = $this->actingAs($user)
            ->get('/dashboard?locale=de');

        $response->assertOk();
        
        // Check that locale was set
        $this->assertEquals('de', App::getLocale());
        
        // Check that session was updated
        $this->assertEquals('de', Session::get('locale'));
        
        // Check that user preference was updated
        $user->refresh();
        $this->assertEquals('de', $user->locale);
    }

    public function test_browser_language_is_used_when_no_user_preference_or_session()
    {
        // Make a request with Chinese browser language, no user, no session
        $response = $this->withHeaders(['Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8'])
            ->get('/login');

        $response->assertOk();

        // The application should use browser language (zh-CN)
        $this->assertEquals('zh-CN', App::getLocale());

        // Session should be updated with detected locale
        $this->assertEquals('zh-CN', Session::get('locale'));
    }

    public function test_fallback_to_default_when_browser_language_not_supported()
    {
        // Make a request with unsupported browser language
        $response = $this->withHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9'])
            ->get('/login');

        $response->assertOk();

        // The application should use default locale (en)
        $this->assertEquals('en', App::getLocale());
    }

    public function test_partial_browser_language_matching()
    {
        // Make a request with 'zh' which should match 'zh-CN'
        $response = $this->withHeaders(['Accept-Language' => 'zh,en;q=0.9'])
            ->get('/login');

        $response->assertOk();

        // The application should use zh-CN (partial match)
        $this->assertEquals('zh-CN', App::getLocale());
    }

    public function test_invalid_url_locale_parameter_is_ignored()
    {
        $user = User::factory()->create(['locale' => 'en']);

        // Make a request with invalid locale parameter
        $response = $this->actingAs($user)
            ->get('/dashboard?locale=invalid-locale');

        $response->assertOk();
        
        // Should fall back to user preference
        $this->assertEquals('en', App::getLocale());
        
        // User preference should not be changed
        $user->refresh();
        $this->assertEquals('en', $user->locale);
    }

    public function test_locale_persistence_across_requests()
    {
        $user = User::factory()->create(['locale' => 'fr']);

        // First request - should use user preference
        $response1 = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals('fr', App::getLocale());

        // Second request - should still use user preference
        $response2 = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
            ->get('/subscriptions');

        $response2->assertOk();
        $this->assertEquals('fr', App::getLocale());
    }

    public function test_guest_user_browser_language_detection()
    {
        // Test with multiple languages in Accept-Language header
        $response = $this->withHeaders([
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8,fr;q=0.7'
        ])->get('/login');

        $response->assertOk();
        
        // Should use Spanish (highest priority supported language)
        $this->assertEquals('es', App::getLocale());
    }

    public function test_supported_locales_constant_matches_validation()
    {
        // Get supported locales from middleware
        $supportedLocales = array_keys(SetLocale::SUPPORTED_LOCALES);

        // This test ensures that all supported locales are properly configured
        $expectedLocales = ['en', 'zh-CN', 'es', 'fr', 'de', 'ja'];

        $this->assertEquals($expectedLocales, $supportedLocales);
    }

    public function test_user_language_preference_persists_across_sessions()
    {
        // Create a user with German locale preference
        $user = User::factory()->create(['locale' => 'de']);

        // Simulate first session with different browser language
        $response1 = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8'])
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals('de', App::getLocale());

        // Clear session to simulate new session
        Session::flush();

        // Simulate second session with different browser language
        $response2 = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8'])
            ->get('/dashboard');

        $response2->assertOk();

        // User preference should still take priority
        $this->assertEquals('de', App::getLocale());

        // Session should be updated with user preference
        $this->assertEquals('de', Session::get('locale'));
    }

    public function test_profile_update_changes_active_locale()
    {
        // Create a user with English locale
        $user = User::factory()->create(['locale' => 'en']);

        // Update user's locale preference via profile update
        $response = $this->actingAs($user)
            ->patchWithCsrf('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'date_format' => $user->date_format ?? 'Y-m-d',
                'locale' => 'ja',
            ]);

        $response->assertRedirect('/settings/profile');

        // Verify user preference was updated
        $user->refresh();
        $this->assertEquals('ja', $user->locale);

        // Make a new request to verify the locale is applied
        $response2 = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals('ja', App::getLocale());
    }
}

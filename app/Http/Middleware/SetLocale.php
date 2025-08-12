<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales
     */
    const SUPPORTED_LOCALES = [
        'en' => 'English',
        'zh-CN' => '中文',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ja' => '日本語',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        
        App::setLocale($locale);
        
        // Set locale for Carbon dates
        \Carbon\Carbon::setLocale($locale);
        
        // Share locale data with all views
        view()->share('currentLocale', $locale);
        view()->share('supportedLocales', self::SUPPORTED_LOCALES);
        
        return $next($request);
    }
    
    /**
     * Determine the locale to use for the request
     *
     * Priority order:
     * 1. URL parameter (for immediate language switching)
     * 2. User preference (if authenticated) - takes precedence over session
     * 3. Session (for guest users or when user has no preference)
     * 4. Browser preference (Accept-Language header)
     * 5. Default application locale
     */
    private function determineLocale(Request $request): string
    {
        // Priority 1: URL parameter (for immediate language switching)
        if ($request->has('locale') && $this->isValidLocale($request->get('locale'))) {
            $locale = $request->get('locale');
            Session::put('locale', $locale);

            // Update user's preference if authenticated
            if ($request->user()) {
                $request->user()->update(['locale' => $locale]);
            }

            return $locale;
        }

        // Priority 2: User preference (if authenticated) - should take precedence over session
        if ($request->user() && $this->isValidLocale($request->user()->locale)) {
            $locale = $request->user()->locale;
            Session::put('locale', $locale);
            return $locale;
        }

        // Priority 3: Session (for guest users or when user has no preference)
        if (Session::has('locale') && $this->isValidLocale(Session::get('locale'))) {
            return Session::get('locale');
        }

        // Priority 4: Browser preference
        $browserLocale = $this->detectBrowserLocale($request);
        if ($browserLocale) {
            Session::put('locale', $browserLocale);
            return $browserLocale;
        }

        // Default
        return config('app.locale', 'en');
    }
    
    /**
     * Check if a locale is valid
     */
    private function isValidLocale(?string $locale): bool
    {
        return $locale && array_key_exists($locale, self::SUPPORTED_LOCALES);
    }
    
    /**
     * Detect locale from browser Accept-Language header
     */
    private function detectBrowserLocale(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');
        
        if (!$acceptLanguage) {
            return null;
        }
        
        // Parse Accept-Language header
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $segments = explode(';', trim($part));
            $lang = $segments[0];
            $priority = 1.0;
            
            if (isset($segments[1])) {
                preg_match('/q=([0-9.]+)/', $segments[1], $matches);
                if (isset($matches[1])) {
                    $priority = (float) $matches[1];
                }
            }
            
            $languages[$lang] = $priority;
        }
        
        // Sort by priority
        arsort($languages);
        
        // Find first matching supported locale
        foreach ($languages as $lang => $priority) {
            // Try exact match
            if ($this->isValidLocale($lang)) {
                return $lang;
            }
            
            // Try language part only (e.g., 'zh' for 'zh-CN')
            $langPart = explode('-', $lang)[0];
            foreach (array_keys(self::SUPPORTED_LOCALES) as $supportedLocale) {
                if (str_starts_with($supportedLocale, $langPart)) {
                    return $supportedLocale;
                }
            }
        }
        
        return null;
    }
}
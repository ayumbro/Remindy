<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => function () {
                return (new Ziggy)->toArray();
            },
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'errors' => $request->session()->get('errors') ? $request->session()->get('errors')->getBag('default') : (object) [],
            'locale' => [
                'current' => app()->getLocale(),
                'supported' => \App\Http\Middleware\SetLocale::SUPPORTED_LOCALES,
            ],
            'translations' => $this->getTranslations(),
        ]);
    }
    
    /**
     * Get translations for the current locale
     */
    private function getTranslations(): array
    {
        $locale = app()->getLocale();
        $translations = [];
        
        // Load specific translation files that we want available in the frontend
        $files = ['app', 'auth', 'subscriptions'];
        
        foreach ($files as $file) {
            $path = lang_path("{$locale}/{$file}.php");
            if (file_exists($path)) {
                $translations[$file] = require $path;
            } else {
                // Fallback to English if translation doesn't exist
                $fallbackPath = lang_path("en/{$file}.php");
                if (file_exists($fallbackPath)) {
                    $translations[$file] = require $fallbackPath;
                }
            }
        }
        
        return $translations;
    }
}

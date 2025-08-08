<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('profile/edit', [
            'user' => $request->user(),
            'dateFormats' => $this->getDateFormats(),
            'locales' => $this->getLocales(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $validDateFormats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
        $validLocales = ['en', 'zh-CN'];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,'.$request->user()->id,
            'date_format' => 'required|string|in:'.implode(',', $validDateFormats),
            'locale' => 'required|string|in:'.implode(',', $validLocales),
        ]);

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('welcome');
    }

    /**
     * Get available date formats.
     */
    private function getDateFormats(): array
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD (2024-01-15)',
            'm/d/Y' => 'MM/DD/YYYY (01/15/2024)',
            'd/m/Y' => 'DD/MM/YYYY (15/01/2024)',
        ];
    }

    /**
     * Get available locales.
     */
    private function getLocales(): array
    {
        return [
            'en' => 'English',
            'zh-CN' => '简体中文',
        ];
    }
}

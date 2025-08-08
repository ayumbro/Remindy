<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    /**
     * Display the currency settings page.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $currencies = Currency::active()->withCount(['subscriptions', 'paymentHistories'])->get();

        return Inertia::render('settings/currencies', [
            'allCurrencies' => $currencies->values(),
            'userCurrencySettings' => [
                'default_currency_id' => $user->default_currency_id,
                'enabled_currencies' => $user->enabled_currencies ?? $currencies->pluck('id')->toArray(),
            ],
        ]);
    }

    /**
     * Update the user's currency preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'default_currency_id' => 'nullable|exists:currencies,id',
            'enabled_currencies' => 'nullable|array',
            'enabled_currencies.*' => 'exists:currencies,id',
        ]);

        // Ensure at least one currency is enabled
        if (empty($validated['enabled_currencies'])) {
            return back()->withErrors(['enabled_currencies' => 'At least one currency must be enabled.']);
        }

        // If default currency is set, ensure it's in the enabled currencies
        if ($validated['default_currency_id'] && ! in_array($validated['default_currency_id'], $validated['enabled_currencies'])) {
            return back()->withErrors(['default_currency_id' => 'Default currency must be one of the enabled currencies.']);
        }

        $user->update([
            'default_currency_id' => $validated['default_currency_id'],
            'enabled_currencies' => $validated['enabled_currencies'],
        ]);

        return back()->with('success', 'Currency preferences updated successfully.');
    }

    /**
     * Store a newly created currency.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code|regex:/^[A-Z]{3}$/',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
        ], [
            'code.size' => 'Currency code must be exactly 3 characters.',
            'code.unique' => 'This currency code already exists.',
            'code.regex' => 'Currency code must be 3 uppercase letters (e.g., EUR, GBP).',
        ]);

        $currency = Currency::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'is_active' => true,
            'user_id' => $user->id,
            'is_system_default' => false,
        ]);

        return back()->with('success', "Currency {$currency->code} created successfully!");
    }

    /**
     * Update a specific currency.
     */
    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this currency or it's a system currency they can edit
        if ($currency->user_id && $currency->user_id !== $user->id) {
            abort(403, 'Unauthorized access to currency.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
        ]);

        $currency->update($validated);

        return back()->with('success', "Currency {$currency->code} updated successfully!");
    }

    /**
     * Remove the specified currency.
     */
    public function destroy(Currency $currency): RedirectResponse
    {
        // Check if currency can be deleted
        if (! $currency->canBeDeleted()) {
            return back()->withErrors(['currency' => $currency->getDeletionBlockReason()]);
        }

        $currencyCode = $currency->code;
        $currency->delete();

        return back()->with('success', "Currency {$currencyCode} deleted successfully!");
    }
}

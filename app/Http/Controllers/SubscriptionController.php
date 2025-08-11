<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentAttachment;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get all subscriptions first, then filter by computed_status in PHP
        $query = Subscription::with(['currency', 'paymentMethod', 'categories'])
            ->forUser($user->id)
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->categories, function ($query, $categories) {
                return $query->whereHas('categories', function ($q) use ($categories) {
                    $q->whereIn('categories.id', $categories);
                });
            });

        // Handle status filtering with improved logic
        $orderBy = 'name'; // Default ordering
        $orderDirection = 'asc';
        $useComputedFiltering = false;

        if ($request->status) {
            switch ($request->status) {
                case 'ended':
                    // For ended filter: has end_date AND end_date is in past
                    $query->whereNotNull('end_date')
                        ->where('end_date', '<=', Carbon::now());
                    break;

                case 'active':
                    // For active filter: only non-ended subscriptions
                    $query->active();
                    break;

                case 'upcoming':
                    // For upcoming filter: active subscriptions with future billing dates
                    $query->active();
                    $useComputedFiltering = true;
                    break;

                case 'overdue':
                    // For overdue filter: active subscriptions with past billing dates
                    $query->active();
                    $useComputedFiltering = true;
                    break;

                case 'all':
                default:
                    // For "all" filter: show everything, no additional filtering
                    break;
            }
        } else {
            // Default to showing all subscriptions when no filter is applied
            // This fixes the "All" filter behavior
        }

        // Apply ordering and filtering based on filter type
        if ($useComputedFiltering) {
            // For upcoming and overdue, we need to filter in PHP since next_billing_date is computed
            $allSubscriptions = $query->get();

            if ($request->status === 'upcoming') {
                // Filter to only include subscriptions with future billing dates (>= today)
                $filteredSubscriptions = $allSubscriptions->filter(function ($subscription) {
                    $nextBillingDate = $subscription->next_billing_date;

                    return $nextBillingDate && Carbon::parse($nextBillingDate)->gte(Carbon::now()->startOfDay());
                })->sortBy(function ($subscription) {
                    return $subscription->next_billing_date;
                })->values();
            } elseif ($request->status === 'overdue') {
                // Filter to only include subscriptions with past billing dates (< today)
                $filteredSubscriptions = $allSubscriptions->filter(function ($subscription) {
                    $nextBillingDate = $subscription->next_billing_date;

                    return $nextBillingDate && Carbon::parse($nextBillingDate)->lt(Carbon::now()->startOfDay());
                })->sortBy(function ($subscription) {
                    return $subscription->next_billing_date;
                })->values();
            }

            // Manually paginate the filtered results
            $currentPage = request()->get('page', 1);
            $perPage = 15;
            $total = $filteredSubscriptions->count();
            $items = $filteredSubscriptions->slice(($currentPage - 1) * $perPage, $perPage);

            $subscriptions = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'pageName' => 'page']
            );
            $subscriptions->appends(request()->query());
        } else {
            // Standard database ordering for other filters
            $subscriptions = $query->orderBy($orderBy, $orderDirection)->paginate(15);
        }

        return Inertia::render('subscriptions/index', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only(['status', 'search', 'categories']),
            'categories' => Category::forUser($user->id)->active()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $user = Auth::user();

        // Get enabled currencies for the user
        $enabledCurrencyIds = $user->getEnabledCurrencyIds();
        $currencies = Currency::active()->whereIn('id', $enabledCurrencyIds)->get();

        // Get user's default notification settings
        $defaultNotificationSettings = [
            'email_enabled' => $user->default_email_enabled,
            'webhook_enabled' => $user->default_webhook_enabled,
            'reminder_intervals' => $user->getDefaultReminderIntervalsWithFallback(),
            'notification_email' => $user->getEffectiveNotificationEmail(),
            'webhook_url' => $user->webhook_url,
        ];

        $availableIntervals = [
            ['value' => 30, 'label' => '30 days before'],
            ['value' => 15, 'label' => '15 days before'],
            ['value' => 7, 'label' => '1 week before'],
            ['value' => 3, 'label' => '3 days before'],
            ['value' => 2, 'label' => '2 days before'],
            ['value' => 1, 'label' => '1 day before'],
        ];

        return Inertia::render('subscriptions/create', [
            'currencies' => $currencies,
            'paymentMethods' => PaymentMethod::forUser($user->id)->active()->get(),
            'categories' => Category::forUser($user->id)->active()->get(),
            'userCurrencySettings' => [
                'default_currency_id' => $user->default_currency_id,
                'enabled_currencies' => $enabledCurrencyIds,
            ],
            'defaultNotificationSettings' => $defaultNotificationSettings,
            'availableIntervals' => $availableIntervals,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Convert "none" to null for payment_method_id
        $requestData = $request->all();
        if (isset($requestData['payment_method_id']) && $requestData['payment_method_id'] === 'none') {
            $requestData['payment_method_id'] = null;
        }

        $validated = validator($requestData, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'billing_cycle' => 'required|in:daily,weekly,monthly,quarterly,yearly,one-time',
            'billing_interval' => 'required|integer|min:1|max:12',
            'start_date' => 'required|date',
            'first_billing_date' => 'nullable|date|after_or_equal:start_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'website_url' => 'nullable|url|max:255',
            'notes' => 'nullable|string|max:1000',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            // Notification settings
            'notifications_enabled' => 'boolean',
            'use_default_notifications' => 'boolean',
            'email_enabled' => 'boolean',
            'reminder_intervals' => 'nullable|array',
            'reminder_intervals.*' => 'integer|in:1,2,3,7,15,30',
        ], [
            'first_billing_date.after_or_equal' => 'The first billing date must be on or after the start date.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ])->validate();

        // Additional validation: ensure payment method belongs to user
        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find($validated['payment_method_id']);
            if (! $paymentMethod || $paymentMethod->user_id !== $user->id) {
                return back()->withErrors(['payment_method_id' => 'The selected payment method is invalid.']);
            }
        }

        // Additional validation: ensure categories belong to user
        if (! empty($validated['category_ids'])) {
            $userCategoryIds = Category::forUser($user->id)->pluck('id')->toArray();
            $invalidCategories = array_diff($validated['category_ids'], $userCategoryIds);
            if (! empty($invalidCategories)) {
                return back()->withErrors(['category_ids' => 'Some selected categories are invalid.']);
            }
        }

        // Set first_billing_date to start_date if not provided
        if (empty($validated['first_billing_date'])) {
            $validated['first_billing_date'] = $validated['start_date'];
        }

        $subscription = $user->subscriptions()->create($validated);

        // Set the billing cycle day based on the start date for monthly/quarterly cycles
        // This field is immutable once set and ensures consistent billing dates
        $subscription->setBillingCycleDay();
        $subscription->save();

        if (! empty($validated['category_ids'])) {
            $subscription->categories()->attach($validated['category_ids']);
        }

        // Handle notification preferences
        $notificationData = [
            'notifications_enabled' => $validated['notifications_enabled'] ?? true,
            'use_default_notifications' => $validated['use_default_notifications'] ?? true,
            'email_enabled' => $validated['email_enabled'] ?? true,
            'reminder_intervals' => $validated['reminder_intervals'] ?? null,
        ];

        // Set notification preferences for the subscription
        if ($notificationData['notifications_enabled'] && ! $notificationData['use_default_notifications']) {
            // Use custom notification settings
            $subscription->update([
                'use_default_notifications' => false,
                'notifications_enabled' => true,
                'email_enabled' => $notificationData['email_enabled'],
                'webhook_enabled' => false, // Not implemented yet
                'reminder_intervals' => $notificationData['reminder_intervals'] ?? $user->default_reminder_intervals ?? [7, 3, 1],
            ]);
        } else {
            // Use default notification settings
            $subscription->update([
                'use_default_notifications' => true,
            ]);
        }

        return to_route('subscriptions.index')
            ->with('success', 'Subscription created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscription $subscription): Response
    {
        $this->authorize('view', $subscription);

        $subscription->load([
            'currency',
            'paymentMethod',
            'categories',
            'paymentHistories' => function ($query) {
                $query->with(['currency', 'paymentMethod', 'attachments'])
                    ->orderBy('payment_date', 'desc')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        return Inertia::render('subscriptions/show', [
            'subscription' => $subscription,
        ]);
    }

    /**
     * Show the form for creating a new payment for the subscription.
     */
    public function createPayment(Subscription $subscription): Response
    {
        $this->authorize('view', $subscription);

        // Load subscription relationships
        $subscription->load(['currency', 'paymentMethod']);

        // Load payment methods and currencies for the payment form
        $paymentMethods = $subscription->user->paymentMethods()->where('is_active', true)->get();
        $currencies = \App\Models\Currency::all();

        return Inertia::render('subscriptions/payments/create', [
            'subscription' => $subscription,
            'paymentMethods' => $paymentMethods,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Show the form for editing a payment for the subscription.
     */
    public function editPayment(Subscription $subscription, PaymentHistory $payment): Response
    {
        $this->authorize('view', $subscription);

        // Ensure the payment belongs to this subscription
        if ($payment->subscription_id !== $subscription->id) {
            abort(404);
        }

        // Load subscription relationships
        $subscription->load(['currency', 'paymentMethod']);

        // Load payment with its relationships
        $payment->load(['currency', 'paymentMethod', 'attachments']);

        // Load payment methods and currencies for the payment form
        $paymentMethods = $subscription->user->paymentMethods()->where('is_active', true)->get();
        $currencies = \App\Models\Currency::all();

        return Inertia::render('subscriptions/payments/edit', [
            'subscription' => $subscription,
            'payment' => $payment,
            'paymentMethods' => $paymentMethods,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subscription $subscription): Response
    {
        $this->authorize('update', $subscription);

        $user = Auth::user();
        $subscription->load('categories');

        // Format dates for frontend using centralized DateHelper
        $subscriptionData = $subscription->toArray();
        $subscriptionData['start_date'] = DateHelper::formatDate($subscription->start_date);
        $subscriptionData['first_billing_date'] = DateHelper::formatDate($subscription->first_billing_date);
        $subscriptionData['next_billing_date'] = DateHelper::formatDate($subscription->next_billing_date);
        $subscriptionData['end_date'] = DateHelper::formatDate($subscription->end_date);
        $subscriptionData['categories'] = $subscription->categories;
        // Get effective notification settings for the subscription
        $subscriptionData['notification_settings'] = $subscription->getEffectiveNotificationSettings();

        // Get user's default notification settings
        $defaultNotificationSettings = [
            'email_enabled' => $user->default_email_enabled,
            'webhook_enabled' => $user->default_webhook_enabled,
            'reminder_intervals' => $user->getDefaultReminderIntervalsWithFallback(),
            'notification_email' => $user->getEffectiveNotificationEmail(),
            'webhook_url' => $user->webhook_url,
        ];

        $availableIntervals = [
            ['value' => 30, 'label' => '30 days before'],
            ['value' => 15, 'label' => '15 days before'],
            ['value' => 7, 'label' => '1 week before'],
            ['value' => 3, 'label' => '3 days before'],
            ['value' => 2, 'label' => '2 days before'],
            ['value' => 1, 'label' => '1 day before'],
        ];

        // Get enabled currencies for the user
        $enabledCurrencyIds = $user->getEnabledCurrencyIds();
        $currencies = Currency::active()->whereIn('id', $enabledCurrencyIds)->get();

        return Inertia::render('subscriptions/edit', [
            'subscription' => $subscriptionData,
            'currencies' => $currencies,
            'paymentMethods' => PaymentMethod::forUser($user->id)->active()->get(),
            'categories' => Category::forUser($user->id)->active()->get(),
            'userCurrencySettings' => [
                'default_currency_id' => $user->default_currency_id,
                'enabled_currencies' => $enabledCurrencyIds,
            ],
            'defaultNotificationSettings' => $defaultNotificationSettings,
            'availableIntervals' => $availableIntervals,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('update', $subscription);

        // Convert "none" to null for payment_method_id
        $requestData = $request->all();
        if (isset($requestData['payment_method_id']) && $requestData['payment_method_id'] === 'none') {
            $requestData['payment_method_id'] = null;
        }

        $validated = validator($requestData, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'end_date' => 'nullable|date|after_or_equal:'.$subscription->start_date,
            'website_url' => 'nullable|url',
            'notes' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            // Notification settings
            'notifications_enabled' => 'boolean',
            'use_default_notifications' => 'boolean',
            'email_enabled' => 'boolean',
            'reminder_intervals' => 'nullable|array',
            'reminder_intervals.*' => 'integer|in:1,2,3,7,15,30',
        ], [
            'end_date.after_or_equal' => 'The end date must be on or after the start date ('.$subscription->start_date.').',
        ])->validate();

        // Remove any immutable fields that might have been sent (extra security)
        // These fields are immutable once set during subscription creation
        unset($validated['start_date']);
        unset($validated['first_billing_date']);
        unset($validated['billing_cycle']);
        unset($validated['billing_interval']);
        unset($validated['billing_cycle_day']);

        // Remove notification fields from main update
        $notificationData = [
            'notifications_enabled' => $validated['notifications_enabled'] ?? false,
            'use_default_notifications' => $validated['use_default_notifications'] ?? true,
            'email_enabled' => $validated['email_enabled'] ?? true,
            'reminder_intervals' => $validated['reminder_intervals'] ?? [7, 3, 1],
        ];
        unset($validated['notifications_enabled'], $validated['use_default_notifications'],
            $validated['email_enabled'], $validated['reminder_intervals']);

        $subscription->update($validated);

        // Handle notification preferences
        if ($notificationData['notifications_enabled'] && ! $notificationData['use_default_notifications']) {
            // Use custom notification settings
            $subscription->update([
                'use_default_notifications' => false,
                'notifications_enabled' => true,
                'email_enabled' => $notificationData['email_enabled'],
                'webhook_enabled' => false, // Not implemented yet
                'reminder_intervals' => $notificationData['reminder_intervals'],
            ]);
        } else {
            // Reset to use default notification settings
            $subscription->update([
                'use_default_notifications' => true,
                'notifications_enabled' => null,
                'email_enabled' => null,
                'webhook_enabled' => null,
                'reminder_intervals' => null,
            ]);
        }

        // Sync categories
        if (isset($validated['category_ids'])) {
            $subscription->categories()->sync($validated['category_ids']);
        } else {
            $subscription->categories()->detach();
        }

        return to_route('subscriptions.show', $subscription)
            ->with('success', 'Subscription updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return to_route('subscriptions.index')
            ->with('success', 'Subscription deleted successfully.');
    }

    /**
     * Mark subscription as paid.
     */
    public function markAsPaid(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'currency_id' => 'required|exists:currencies,id',
            'notes' => 'nullable|string|max:1000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx|max:10240', // 10MB max per file
        ], [
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
        ]);

        // Ensure payment method belongs to the user if provided
        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = \App\Models\PaymentMethod::where('id', $validated['payment_method_id'])
                ->where('user_id', $subscription->user_id)
                ->first();

            if (! $paymentMethod) {
                return back()->withErrors(['payment_method_id' => 'Invalid payment method selected.']);
            }
        }

        $subscription->markAsPaid(
            $validated['amount'],
            $validated['payment_date'],
            $validated['payment_method_id'] ?? null,
            $validated['currency_id']
        );

        // Get the latest payment record
        $latestPayment = $subscription->paymentHistories()->latest()->first();

        // Add notes to the payment history if provided
        if (! empty($validated['notes'])) {
            $latestPayment->update(['notes' => $validated['notes']]);
        }

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($latestPayment, $file);
            }
        }

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Store a file attachment for a payment history record.
     */
    private function storeAttachment(PaymentHistory $paymentHistory, $file): PaymentAttachment
    {
        // Generate unique filename
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = 'payment-attachments/'.$paymentHistory->id.'/'.$filename;

        // Store file in private disk
        Storage::disk('private')->putFileAs(
            'payment-attachments/'.$paymentHistory->id,
            $file,
            $filename
        );

        // Create attachment record
        return PaymentAttachment::create([
            'payment_history_id' => $paymentHistory->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }
}

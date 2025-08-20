<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('subscription'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'start_date' => 'sometimes|required|date',
            'first_billing_date' => [
                'sometimes',
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $this->validateFirstBillingDateConsistency($value, $fail);
                },
            ],
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'website_url' => 'nullable|url',
            'notes' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            // Notification settings
            'notifications_enabled' => 'boolean',
            'use_default_notifications' => 'boolean',
            'email_enabled' => 'boolean',
            'webhook_enabled' => 'boolean',
            'reminder_intervals' => 'nullable|array',
            'reminder_intervals.*' => 'integer|in:1,2,3,7,15,30',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * Validate that the first billing date change is consistent with payment history.
     *
     * @param string $value The new first billing date value
     * @param \Closure $fail The validation failure callback
     * @return void
     */
    protected function validateFirstBillingDateConsistency(string $value, \Closure $fail): void
    {
        $subscription = $this->route('subscription');

        // If the first billing date hasn't changed, no additional validation needed
        if ($value === $subscription->first_billing_date->format('Y-m-d')) {
            return;
        }

        // Get the number of paid payments
        $paidPaymentsCount = $subscription->paymentHistories()->where('status', 'paid')->count();

        // If there are no payments, any valid first billing date is acceptable
        if ($paidPaymentsCount === 0) {
            return;
        }

        // If there are payments, check that the new first billing date doesn't conflict
        $newFirstBillingDate = Carbon::parse($value);

        // Get the earliest payment date
        $earliestPayment = $subscription->paymentHistories()
            ->where('status', 'paid')
            ->orderBy('payment_date')
            ->first();

        if ($earliestPayment) {
            $earliestPaymentDate = Carbon::parse($earliestPayment->payment_date);

            // The new first billing date should not be after the earliest payment date
            if ($newFirstBillingDate->gt($earliestPaymentDate)) {
                $fail('The first billing date cannot be after the earliest payment date (' . $earliestPaymentDate->format('Y-m-d') . ').');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert "none" to null for payment_method_id
        if ($this->input('payment_method_id') === 'none') {
            $this->merge(['payment_method_id' => null]);
        }
    }
}

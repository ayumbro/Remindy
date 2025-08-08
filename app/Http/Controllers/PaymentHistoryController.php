<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttachment;
use App\Models\PaymentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaymentHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentHistory $paymentHistory): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $paymentHistory->subscription;

        // Ensure user owns this payment history through the subscription
        if ($subscription->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment history.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'currency_id' => 'required|exists:currencies,id',
            'notes' => 'nullable|string|max:1000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx|max:10240', // 10MB max per file
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer|exists:payment_attachments,id',
        ], [
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
        ]);

        // Ensure payment method belongs to the user if provided
        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = \App\Models\PaymentMethod::where('id', $validated['payment_method_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $paymentMethod) {
                return back()->withErrors(['payment_method_id' => 'Invalid payment method selected.']);
            }
        }

        $paymentHistory->update($validated);

        // Handle attachment removals
        if ($request->has('remove_attachments')) {
            $attachmentsToRemove = $request->input('remove_attachments');

            foreach ($attachmentsToRemove as $attachmentId) {
                $attachment = PaymentAttachment::where('id', $attachmentId)
                    ->whereHas('paymentHistory', function ($query) use ($user) {
                        $query->whereHas('subscription', function ($subQuery) use ($user) {
                            $subQuery->where('user_id', $user->id);
                        });
                    })
                    ->first();

                if ($attachment) {
                    $attachment->deleteFile();
                    $attachment->delete();
                }
            }
        }

        // Handle new file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($paymentHistory, $file);
            }
        }

        return back()->with('success', 'Payment record updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentHistory $paymentHistory): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $paymentHistory->subscription;

        // Ensure user owns this payment history through the subscription
        if ($subscription->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment history.');
        }

        // Get the most recent payment for this subscription
        $mostRecentPayment = $subscription->paymentHistories()
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // Only allow deletion of the most recent payment
        if (! $mostRecentPayment || $paymentHistory->id !== $mostRecentPayment->id) {
            return back()->withErrors(['payment_history' => 'Only the most recent payment can be deleted.']);
        }

        $paymentHistory->delete();

        return back()->with('success', 'Payment record deleted successfully!');
    }

    /**
     * Add attachments to an existing payment history record.
     */
    public function addAttachments(Request $request, PaymentHistory $payment_history): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $payment_history->subscription;

        // Ensure user owns this payment history through the subscription
        if ($subscription->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment history.');
        }

        $validated = $request->validate([
            'attachments' => 'required|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx|max:10240', // 10MB max per file
        ]);

        // Handle new file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storeAttachment($payment_history, $file);
            }
        }

        return back()->with('success', 'Attachments added successfully!');
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

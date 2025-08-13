<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $paymentMethods = PaymentMethod::forUser($user->id)
            ->withCount(['subscriptions', 'paymentHistories'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('payment-methods/index', [
            'paymentMethods' => $paymentMethods,
            'paymentMethodTypes' => PaymentMethod::getTypes(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('payment-methods/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|in:0,1,true,false',
        ], [
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif.',
            'image.max' => 'The image may not be greater than 2MB.',
        ]);

        // Handle image upload - be very explicit about the logic
        $imagePath = null; // Default to null

        // Only process if we have a valid file
        if ($request->hasFile('image')) {
            $uploadedFile = $request->file('image');
            if ($uploadedFile->isValid()) {
                try {
                    $storedPath = $uploadedFile->store('payment-method-images', 'public');
                    if ($storedPath) {
                        $imagePath = $storedPath;
                    } else {
                        $imagePath = null;
                    }
                } catch (\Exception $e) {
                    Log::error('Payment method image storage failed', ['error' => $e->getMessage()]);
                    $imagePath = null;
                }
            } else {
                $imagePath = null;
            }
        } else {
            $imagePath = null;
        }

        $paymentMethod = PaymentMethod::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'is_active' => isset($validated['is_active']) ? ($validated['is_active'] === '1' || $validated['is_active'] === 'true' || $validated['is_active'] === true) : true,
        ]);

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$paymentMethod->name}' created successfully!");
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentMethod $paymentMethod): Response
    {
        $user = Auth::user();

        // Ensure user owns this payment method
        if ($paymentMethod->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment method.');
        }

        return Inertia::render('payment-methods/show', [
            'paymentMethod' => $paymentMethod->load('subscriptions.currency'),
            'paymentMethodTypes' => PaymentMethod::getTypes(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentMethod $paymentMethod): Response
    {
        $user = Auth::user();

        // Ensure user owns this payment method
        if ($paymentMethod->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment method.');
        }

        return Inertia::render('payment-methods/edit', [
            'paymentMethod' => $paymentMethod,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this payment method
        if ($paymentMethod->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment method.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|in:0,1,true,false',
            'remove_image' => 'nullable|in:0,1,true,false',
        ], [
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif.',
            'image.max' => 'The image may not be greater than 2MB.',
        ]);

        // Handle image upload - be very explicit about the logic
        $imagePath = $paymentMethod->image_path; // Keep existing image by default

        // Check if user wants to remove the existing image
        $shouldRemoveImage = isset($validated['remove_image']) &&
                           ($validated['remove_image'] === '1' || $validated['remove_image'] === 'true' || $validated['remove_image'] === true);

        if ($shouldRemoveImage) {
            try {
                // Delete existing image and set path to null
                $paymentMethod->deleteImage();
                $imagePath = null;
            } catch (\Exception $e) {
                Log::error('Payment method image deletion failed', [
                    'payment_method_id' => $paymentMethod->id,
                    'error' => $e->getMessage(),
                ]);

                return back()->withErrors(['image' => 'Failed to delete the image. Please try again.']);
            }
        }

        // Process new file upload (this overrides image removal if both are present)
        if ($request->hasFile('image')) {
            $uploadedFile = $request->file('image');
            if ($uploadedFile->isValid()) {
                try {
                    // Delete old image if it exists (only if we haven't already deleted it above)
                    if (! $shouldRemoveImage) {
                        $paymentMethod->deleteImage();
                    }

                    // Store new image
                    $storedPath = $uploadedFile->store('payment-method-images', 'public');
                    if ($storedPath) {
                        $imagePath = $storedPath;
                    }
                    // Keep existing image path if storage fails
                } catch (\Exception $e) {
                    Log::error('Payment method image upload failed', [
                        'payment_method_id' => $paymentMethod->id,
                        'error' => $e->getMessage(),
                    ]);

                    return back()->withErrors(['image' => 'Failed to upload the image. Please try again.']);
                }
            }
            // Keep existing image path if file is invalid or not present
        }

        $paymentMethod->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'is_active' => isset($validated['is_active']) ? ($validated['is_active'] === '1' || $validated['is_active'] === 'true' || $validated['is_active'] === true) : true,
        ]);

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$paymentMethod->name}' updated successfully!");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this payment method
        if ($paymentMethod->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment method.');
        }

        // Check if payment method can be deleted
        if (! $paymentMethod->canBeDeleted()) {
            return back()->withErrors(['payment_method' => $paymentMethod->getDeletionBlockReason()]);
        }

        $paymentMethodName = $paymentMethod->name;

        // Delete associated image
        $paymentMethod->deleteImage();

        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$paymentMethodName}' deleted successfully!");
    }

    /**
     * Toggle the active status of the specified payment method.
     */
    public function toggleStatus(PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this payment method
        if ($paymentMethod->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment method.');
        }

        $newStatus = ! $paymentMethod->is_active;
        $action = $newStatus ? 'enabled' : 'disabled';

        $paymentMethod->update(['is_active' => $newStatus]);

        return back()->with('success', "Payment method '{$paymentMethod->name}' has been {$action}.");
    }

    /**
     * Store a new payment method via API for inline creation.
     */
    public function apiStore(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($_, $value, $fail) use ($user) {
                    // Check for duplicate names for this user
                    $exists = PaymentMethod::where('user_id', $user->id)
                        ->where('name', trim($value))
                        ->exists();

                    if ($exists) {
                        $fail('A payment method with this name already exists.');
                    }
                },
            ],
        ]);

        try {
            $paymentMethod = PaymentMethod::create([
                'user_id' => $user->id,
                'name' => trim($validated['name']),
                'description' => null,
                'image_path' => null,
                'is_active' => true,
            ]);

            // Return JSON response for axios consumption
            return response()->json([
                'success' => true,
                'payment_method' => [
                    'id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'description' => $paymentMethod->description,
                    'is_active' => $paymentMethod->is_active,
                ],
                'message' => "Payment method '{$paymentMethod->name}' created successfully!",
            ]);
        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method. Please try again.'
            ], 500);
        }
    }
}

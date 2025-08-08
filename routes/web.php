<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\PaymentAttachmentController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');



    // Resource routes
    Route::resource('subscriptions', SubscriptionController::class);
    Route::post('subscriptions/{subscription}/mark-paid', [SubscriptionController::class, 'markAsPaid'])
        ->name('subscriptions.mark-paid');

    Route::resource('categories', CategoryController::class);
    Route::resource('payment-methods', PaymentMethodController::class);

    // Additional POST route for payment method updates with FormData (file uploads)
    Route::post('payment-methods/{payment_method}', [PaymentMethodController::class, 'update'])
        ->name('payment-methods.update-with-files');

    // Route for toggling payment method status
    Route::patch('payment-methods/{payment_method}/toggle-status', [PaymentMethodController::class, 'toggleStatus'])
        ->name('payment-methods.toggle-status');

    Route::resource('payment-histories', PaymentHistoryController::class)->only(['index', 'show', 'update', 'destroy']);

    // Additional POST route for payment history updates with FormData (file uploads)
    Route::post('payment-histories/{payment_history}', [PaymentHistoryController::class, 'update'])
        ->name('payment-histories.update-with-files');

    Route::post('payment-histories/{payment_history}/attachments', [PaymentHistoryController::class, 'addAttachments'])
        ->name('payment-histories.add-attachments');

    // Payment attachment routes
    Route::get('payment-attachments/{paymentAttachment}/download', [PaymentAttachmentController::class, 'download'])
        ->name('payment-attachments.download');
    Route::delete('payment-attachments/{paymentAttachment}', [PaymentAttachmentController::class, 'destroy'])
        ->name('payment-attachments.destroy');

    // Settings routes
    Route::get('settings/currencies', [CurrencyController::class, 'index'])->name('settings.currencies.index');
    Route::patch('settings/currencies', [CurrencyController::class, 'update'])->name('settings.currencies.update');
    Route::post('settings/currencies', [CurrencyController::class, 'store'])->name('settings.currencies.store');
    Route::patch('settings/currencies/{currency}', [CurrencyController::class, 'updateCurrency'])->name('settings.currencies.update-currency');
    Route::delete('settings/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('settings.currencies.destroy');

    // Notification settings
    Route::get('settings/notifications', [NotificationController::class, 'index'])->name('settings.notifications.index');
    Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('settings.notifications.update');
    Route::post('settings/notifications/test-email', [NotificationController::class, 'testEmail'])->name('settings.notifications.test-email');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

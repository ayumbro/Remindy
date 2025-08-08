<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaymentAttachmentController extends Controller
{
    /**
     * Download a payment attachment.
     */
    public function download(PaymentAttachment $paymentAttachment): Response
    {
        $user = Auth::user();
        $paymentHistory = $paymentAttachment->paymentHistory;
        $subscription = $paymentHistory->subscription;

        // Ensure user owns this attachment through the subscription
        if ($subscription->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment attachment.');
        }

        // Check if file exists
        if (! $paymentAttachment->fileExists()) {
            abort(404, 'File not found.');
        }

        // Get file content
        $fileContent = Storage::disk('private')->get($paymentAttachment->file_path);

        return response($fileContent, 200, [
            'Content-Type' => $paymentAttachment->file_type,
            'Content-Disposition' => 'attachment; filename="'.$paymentAttachment->original_name.'"',
            'Content-Length' => $paymentAttachment->file_size,
        ]);
    }

    /**
     * Delete a payment attachment.
     */
    public function destroy(PaymentAttachment $paymentAttachment)
    {
        $user = Auth::user();
        $paymentHistory = $paymentAttachment->paymentHistory;
        $subscription = $paymentHistory->subscription;

        // Ensure user owns this attachment through the subscription
        if ($subscription->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payment attachment.');
        }

        // Delete file from storage
        $paymentAttachment->deleteFile();

        // Delete database record
        $paymentAttachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DeleteProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProfileDeletionController extends Controller
{
    /**
     * Display the profile deletion request form.
     */
    public function showForm()
    {
        return view('remove-profile');
    }

    /**
     * Handle the profile deletion request submission.
     */
    public function submitRequest(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'in:no_longer_needed,privacy_concerns,switching_apps,technical_issues,other'],
            'additional_info' => ['nullable', 'string', 'max:1000'],
        ], [
            'email.required' => 'Please provide a valid email address.',
            'email.email' => 'Please provide a valid email address.',
            'additional_info.max' => 'Additional information must not exceed 1000 characters.',
        ]);

        try {
            // Create the deletion request
            $deletionRequest = DeleteProfileRequest::create([
                'email' => $validated['email'],
                'reason' => $validated['reason'] ?? null,
                'additional_info' => $validated['additional_info'] ?? null,
                'ip_address' => $request->ip(),
                'status' => 'pending',
            ]);

            // Log the request for monitoring
            Log::info('Profile deletion request submitted', [
                'request_id' => $deletionRequest->id,
                'email' => $validated['email'],
                'ip_address' => $request->ip(),
            ]);

            // Optional: Send notification email to admin
            // $this->notifyAdmin($deletionRequest);

            // Redirect back with success message
            return redirect()->back()->with('success',
                'Your profile deletion request has been submitted successfully. We will process it within 30 days.'
            );

        } catch (\Exception $e) {
            // Log the error
            Log::error('Error submitting profile deletion request', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            // Redirect back with error message
            return redirect()->back()
                ->withInput()
                ->with('error', 'There was an error submitting your request. Please try again later.');
        }
    }

    /**
     * Optional: Notify admin about new deletion request.
     */
    private function notifyAdmin(DeleteProfileRequest $request)
    {
        // Implement email notification to admin
        // Mail::to('admin@example.com')->send(new ProfileDeletionRequestNotification($request));
    }
}

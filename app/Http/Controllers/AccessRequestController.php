<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccessRequestMail;
use Illuminate\Http\JsonResponse;

class AccessRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'organisation'    => 'required|string|max:255',
            'job_title'  => 'required|string|max:255',
            'phone_number'      => 'nullable|string|max:50',
            'details'    => 'required|string|max:2000',
        ]);

        // Pulls the destination from your .env, falls back to a default if missing
        $destinationEmail = env('ACCESS_REQUEST_EMAIL', 'info@globaltourcreatives.com');
        
        Mail::to($destinationEmail)->send(new AccessRequestMail($validated));

        return response()->json(['message' => 'Request received successfully.']);
    }
}
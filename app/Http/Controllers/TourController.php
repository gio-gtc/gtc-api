<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /**
     * Store a newly created tour in the database.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validate the incoming request matching your exact domain rules
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'expire_on_sale_now_cuts' => 'required|date',
            
            // Foreign keys must exist in their respective tables
            'gtc_rep_id' => 'required|exists:users,id',
            'voice_over_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            
            // Booleans
            'hold_all_invoices' => 'boolean',
            'live_on_ordering_system' => 'boolean',
            'require_client_approval' => 'boolean',
            
            // Required ONLY when require_client_approval is true
            'client_approval_email' => 'required_if:require_client_approval,true|nullable|email|max:255',
            'tour_sponsor' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string',
            
            // Financial data validates as clean decimal/numeric values
            'tv_first_cut' => 'nullable|numeric|min:0',
            'tv_second_cut' => 'nullable|numeric|min:0',
            'radio_single_duration' => 'nullable|numeric|min:0',
            'radio_dual_duration' => 'nullable|numeric|min:0',
            'key_art' => 'nullable|numeric|min:0',
        ]);

        // 2. Create the tour record
        $tour = Tour::create($validated);

        // 3. Eager load the related data so the frontend gets complete names/details instantly
        $tour->load(['gtcRep:id,first_name,last_name', 'voiceOver:id,first_name,last_name', 'department:id,name']);

        // 4. Return the response
        return response()->json([
            'message' => 'Tour created successfully.',
            'tour' => $tour
        ], 201);
    }
}
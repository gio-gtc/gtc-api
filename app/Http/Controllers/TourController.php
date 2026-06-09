<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{

    /**
     * Streams a paginated list of tours tailored to user permissions.
     * (Linked to route: GET /api/tours)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tourQuery = Tour::select(['id', 'name', 'created_at', 'updated_at']);

        // GLOBAL SERVER-SIDE ADVANCED FILTERS + SIDEBAR VIEWS LAYER
        if ($request->hasAny(['client_ids', 'assignee_ids', 'statuses', 'is_international', 'tags', 'filter'])) {
            $tourQuery->whereHas('orders', function ($query) use ($request, $user) {
                
                // Intercept "My Tasks" sidebar view request
                if ($request->query('filter') === 'my-tasks') {
                    $query->whereHas('orderItems.assignees', function ($q) use ($user) {
                        $q->where('users.id', $user->id); // Restricts to the logged-in user's ID
                    });
                }

                // Keep your other existing multi-select filter blocks intact below...
                if ($request->filled('client_ids')) {
                    $query->whereIn('ordered_by_id', $request->client_ids);
                }

                if ($request->filled('assignee_ids')) {
                    $query->whereHas('orderItems.assignees', function ($q) use ($request) {
                        $q->whereIn('users.id', $request->assignee_ids);
                    });
                }

                if ($request->filled('statuses')) {
                    $query->whereHas('orderItems.statusLookup.orderStatus', function ($q) use ($request) {
                        $q->whereIn('name', $request->statuses);
                    });
                }

                if ($request->filled('asset_tags')) {
                    $query->whereHas('orderItems', function ($q) use ($request) {
                        $q->where(function ($jsonQuery) use ($request) {
                            foreach ($request->asset_tags as $tag) {
                                $normalizedTag = strtolower($tag);
                                if ($normalizedTag === 'audio') {
                                    $jsonQuery->orWhere('audio_received', false);
                                } elseif ($normalizedTag === 'voice over') {
                                    $jsonQuery->orWhere('voice_over_received', false);
                                } elseif ($normalizedTag === 'art' || $normalizedTag === 'key art') {
                                    $jsonQuery->orWhere('art_received', false);
                                }
                            }
                        });
                    });
                }

                if ($request->has('is_international') && $request->is_international !== null) {
                    $query->whereHas('client.organisation.country', function ($q) use ($request) {
                        $operator = filter_var($request->is_international, FILTER_VALIDATE_BOOLEAN) ? '!=' : '=';
                        $q->where('code', $operator, 'US');
                    });
                }
            });
        }

        // RBAC BOUNDARY (Preserve multi-tenant client constraints)
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Supervisor'])) {
            $tourQuery->whereHas('orders', function ($query) use ($user) {
                $query->where('ordered_by_id', $user->id)
                    ->orWhereHas('client', function ($q) use ($user) {
                        $q->where('organisation_id', $user->organisation_id);
                    });
            });
        }

        $paginatedTours = $tourQuery->latest()->paginate(15);
        return response()->json($paginatedTours, 200);
    }

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
            'voice_over_id' => 'nullable|exists:users,id',
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
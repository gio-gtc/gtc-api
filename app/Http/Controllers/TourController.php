<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        if ($request->hasAny(['client_ids', 'assignee_ids', 'statuses', 'is_international', 'tags', 'asset_tags', 'filter'])) {
            $tourQuery->whereHas('orders', function ($query) use ($request, $user) {
                if ($request->query('filter') === 'my-tasks') {
                    $query->whereHas('orderItems.assignees', function ($q) use ($user) {
                        $q->where('users.id', $user->id); 
                    });
                }
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
                        $q->whereNull('asset_path')
                        ->whereHas('statusLookup', function ($statusQuery) {
                            $statusQuery->whereNotIn('name', ['Cancelled', 'Still In Cart']);
                        })
                        ->where(function ($subQuery) use ($request) {
                            foreach ($request->asset_tags as $tag) {
                                $normalizedTag = strtolower($tag);
                                if ($normalizedTag === 'audio') {
                                    $subQuery->orWhereHas('orderMenuItem', function ($menuQuery) {
                                        $menuQuery->whereIn('order_menu_category_id', [1, 2, 3]);
                                    });
                                } elseif ($normalizedTag === 'art' || $normalizedTag === 'key art') {
                                    $subQuery->orWhereHas('orderMenuItem', function ($menuQuery) {
                                        $menuQuery->whereIn('order_menu_category_id', [4]);
                                    });
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'expire_on_sale_now_cuts' => 'required|date',
            'gtc_rep_id' => 'required|exists:users,id',
            'voice_over_id' => 'nullable|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            'hold_all_invoices' => 'boolean',
            'live_on_ordering_system' => 'boolean',
            'require_client_approval' => 'boolean',
            'client_approval_email' => 'required_if:require_client_approval,true|nullable|email|max:255',
            'tour_sponsor' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string',
            'tv_first_cut' => 'nullable|numeric|min:0',
            'tv_second_cut' => 'nullable|numeric|min:0',
            'radio_single_duration' => 'nullable|numeric|min:0',
            'radio_dual_duration' => 'nullable|numeric|min:0',
            'key_art' => 'nullable|numeric|min:0',
        ]);

        $tour = Tour::create($validated);

        // Clear the cached options list when a new tour drops in
        Cache::forget("user_" . $request->user()->id . "_tour_dropdown_options");

        $tour->load(['gtcRep:id,first_name,last_name', 'voiceOver:id,first_name,last_name', 'department:id,name']);

        return response()->json([
            'message' => 'Tour created successfully.',
            'tour' => $tour
        ], 201);
    }

    /**
     * High-speed Dropdown Options Endpoint
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "user_{$user->id}_tour_dropdown_options";

        $options = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            $tourQuery = Tour::select(['id', 'name'])->orderBy('name', 'asc');

            if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Supervisor'])) {
                $tourQuery->whereHas('orders', function ($query) use ($user) {
                    $query->where('ordered_by_id', $user->id)
                        ->orWhereHas('client', function ($q) use ($user) {
                            $q->where('organisation_id', $user->organisation_id);
                        });
                });
            }

            return $tourQuery->get();
        });

        return response()->json($options, 200);
    }
}
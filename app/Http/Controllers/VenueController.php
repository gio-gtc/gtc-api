<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    /**
     * Display a listing of all available venues.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        if ($search && strlen($search) < 2) {
            return response()->json(['venues' => []], 200);
        }

        $venues = Venue::query()
            ->with('country:id,name') 
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%");
                });
            })
            ->limit(10) 
            ->get(['id', 'name', 'city', 'state', 'country_id']); 

        return response()->json([
            'venues' => $venues
        ], 200);
    }
}
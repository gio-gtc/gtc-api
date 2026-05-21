<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    /**
     * Display a listing of all available venues.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'venues' => Venue::orderBy('name')->get()
        ], 200);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\OrderMenuCategory;
use Illuminate\Http\JsonResponse;

class OrderMenuController extends Controller
{
    public function index(): JsonResponse
    {
        $catalog = OrderMenuCategory::with(['orderMenuItems' => function ($query) {
            $query->orderBy('name', 'asc');
        }])->get();

        return response()->json([
            'catalog' => $catalog
        ], 200);
    }
}
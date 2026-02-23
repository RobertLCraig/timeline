<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     */
    public function index()
    {
        $categories = EventCategory::orderBy('name')->get();
        return response()->json(['categories' => $categories]);
    }
}

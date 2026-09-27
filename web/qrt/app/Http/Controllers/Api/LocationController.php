<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => Location::all(['id', 'location_name'])
        ]);
    }
}

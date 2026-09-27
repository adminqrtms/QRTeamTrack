<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    /**
     * Display a listing of the residents with their incident counts.
     */
    public function index()
    {
        $residents = User::where('role', 'resident')
            ->withCount(['alarms', 'reports'])
            ->latest()
            ->paginate(15);

        return view('admin.residents.index', compact('residents'));
    }

    /**
     * Display the specified resident's profile.
     */
    public function show($id)
    {
        $resident = User::where('role', 'resident')
            ->with(['reports', 'alarms', 'location'])
            ->findOrFail($id);

        return view('admin.residents.show', compact('resident'));
    }

    public function destroy($id)
    {
        $resident = User::findOrFail($id);
        $resident->delete();
        return back()->with('success', 'Resident account deleted successfully.');
    }
}

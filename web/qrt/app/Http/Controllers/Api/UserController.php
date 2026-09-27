<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Models\Attendance;

class UserController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->load('location'));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'phone_number' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'location_id' => [
                $user->role === 'personnel' ? 'nullable' : 'required',
                'exists:locations,id',
            ],
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone_number = $request->phone_number;
        $user->address = $request->address;
        $user->location_id = $request->location_id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->load('location'),
        ]);
    }

    /**
     * Get all active personnel for tracking on the mobile map.
     * Used by Residents to find help and Personnel to see their team.
     */
    public function activePersonnel(Request $request)
    {
        // Only get personnel who have an active attendance record (on duty)
        $personnel = User::where('role', 'personnel')
            ->whereHas('attendances', function ($query) {
                $query->whereNull('time_out');
            })
            ->where('is_active', true)
            ->with('location:id,location_name')
            ->get(['id', 'name', 'last_latitude', 'last_longitude', 'last_seen', 'phone_number', 'location_id']);

        return response()->json(['status' => 'success', 'data' => $personnel]);
    }
}

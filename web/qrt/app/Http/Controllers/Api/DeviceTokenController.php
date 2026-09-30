<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Register (or move) this phone's push notification token for the logged-in user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:512',
            'platform' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // A token belongs to one phone; if someone else logged in on it before, take it over.
        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            ['user_id' => $request->user()->id, 'platform' => $request->platform],
        );

        return response()->json(['message' => 'Device registered']);
    }

    /**
     * Stop sending notifications to this phone (called on logout).
     */
    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string|max:512']);

        DeviceToken::where('token', $request->token)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Device removed']);
    }
}

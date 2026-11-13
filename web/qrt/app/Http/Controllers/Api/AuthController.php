<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Same rules as the app's registration form (lib/utils/validators.dart)
        $request->merge([
            'name' => trim((string) $request->name),
            'email' => strtolower(trim((string) $request->email)),
            'phone_number' => trim((string) $request->phone_number),
            'address' => trim((string) $request->address),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:255', "regex:/^[\pL .'-]+$/u"],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
            'phone_number' => ['required', 'regex:/^09\d{9}$/'],
            'address' => 'required|string|min:5|max:255',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|max:2048',
        ], [
            'name.regex' => 'Use letters only, e.g. Juan Dela Cruz.',
            'email.unique' => 'This email is already registered. Try logging in instead.',
            'password.regex' => 'Use both letters and numbers in your password.',
            'phone_number.regex' => 'Enter an 11-digit mobile number starting with 09.',
            'address.required' => 'Please enter your home address.',
            'address.min' => 'Please enter your complete address.',
            'location_id.required' => 'Please select your station.',
            'location_id.exists' => 'Please select your station.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'resident',
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'location_id' => $request->location_id,
                'avatar' => $avatarPath,
                'is_active' => true,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'data' => $user->load('location'),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->with('location')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'This account has been deactivated. Please contact the barangay admin.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
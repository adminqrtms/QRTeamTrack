<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Location;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GeneratedPersonnelExport;

class PersonnelController extends Controller
{
    /**
     * Display a listing of the personnel.
     */
    public function index()
    {
        // Fetch only users with the role 'personnel'
        $personnel = User::where('role', 'personnel')->latest()->paginate(10);
        return view('admin.personnels.index', compact('personnel'));
    }

    /**
     * Show the form for creating a new personnel.
     */
    public function create()
    {
        $locations = Location::all();
        return view('admin.personnels.create', compact('locations'));
    }

    /**
     * Store a newly created personnel in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'hourly_rate' => 'nullable|numeric|min:0',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'personnel', // Force role to personnel
            'is_active' => true,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
            'hourly_rate' => $request->hourly_rate,
            'location_id' => $request->location_id,
        ];

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        User::create($data);

        return redirect()->route('admin.personnels.index')->with('success', 'Personnel account created successfully.');
    }

    /**
     * Show the form for editing the specified personnel.
     */
    public function edit($id)
    {
        $personnel = User::findOrFail($id);
        $locations = Location::all();
        return view('admin.personnels.edit', compact('personnel', 'locations'));
    }

    /**
     * Update the specified personnel in storage.
     */
    public function update(Request $request, $id)
    {
        $personnel = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Ignore the current user's email so they can update their name without changing email
                Rule::unique('users')->ignore($personnel->id),
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'hourly_rate' => 'nullable|numeric|min:0',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $personnel->name = $request->name;
        $personnel->email = $request->email;
        $personnel->phone_number = $request->phone_number;
        $personnel->address = $request->address;
        $personnel->hourly_rate = $request->hourly_rate;
        $personnel->location_id = $request->location_id;

        if ($request->filled('password')) {
            $personnel->password = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            $personnel->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $personnel->save();

        return redirect()->route('admin.personnels.index')->with('success', 'Personnel account updated successfully.');
    }

    /**
     * Remove the specified personnel from storage.
     */
    public function destroy($id)
    {
        $personnel = User::findOrFail($id);
        $personnel->delete();

        return redirect()->route('admin.personnels.index')->with('success', 'Personnel account deleted successfully.');
    }

    /**
     * Toggle the active status of the personnel.
     * Corresponds to: Inactive/Active Personnel
     */
    public function toggleStatus($id)
    {
        $personnel = User::findOrFail($id);
        $personnel->is_active = !$personnel->is_active;
        $personnel->save();

        $status = $personnel->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Personnel account has been {$status}.");
    }

    /**
     * Track Personnel using GPS Tracker.
     * Displays a map view with the latest location of all personnel.
     */
    public function monitor(Request $request)
    {
        // Fetch personnel with their most recent location
        $personnels = User::where('role', 'personnel')
            ->where('is_active', true)
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->get();

        $personnels->transform(function ($person) {
            $person->last_seen_time = $person->last_seen ? \Carbon\Carbon::parse($person->last_seen)->diffForHumans() : 'Unknown';
            return $person;
        });

        // The monitor map polls this endpoint for live marker updates.
        if ($request->wantsJson()) {
            return response()->json($personnels);
        }

        return view('admin.personnels.monitor', compact('personnels'));
    }

    /**
     * Update personnel location via API.
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();

        // Only update the coordinates if the personnel is currently "On Duty" (Timed In)
        $isOnDuty = Attendance::where('user_id', $user->id)
            ->whereNull('time_out')
            ->exists();

        if ($isOnDuty) {
            $user->update([
                'last_latitude' => $request->latitude,
                'last_longitude' => $request->longitude,
                'last_seen' => now(),
            ]);
        }

        return response()->json(['message' => 'Location updated']);
    }

    /**
     * View Time in/Time Out (Attendance) for a specific personnel.
     */
    public function attendance($id)
    {
        $personnel = User::findOrFail($id);

        // Calculate current month summary
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $monthlyHours = $personnel->attendances()
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('hours_worked');

        $monthlySalary = round($monthlyHours * ($personnel->hourly_rate ?? 0), 2);

        $attendances = $personnel->attendances()->latest()->paginate(15);
        return view('admin.personnels.attendance', compact('personnel', 'attendances', 'monthlyHours', 'monthlySalary'));
    }

    /**
     * Show the form for batch creating personnel.
     */
    public function batchCreate()
    {
        return view('admin.personnels.batch_create');
    }

    /**
     * Store multiple personnel accounts at once.
     */
    public function batchStore(Request $request)
    {
        $request->validate([
            'account_count' => 'required|integer|min:1|max:100',
            'hourly_rate' => 'required|numeric|min:0',
        ]);

        $count = $request->account_count;
        $rate = $request->hourly_rate;
        
        // Determine starting sequence
        $existingCount = User::where('role', 'personnel')->count();
        
        $monthYear = now()->format('mY'); 
        $generatedAccounts = [];

        for ($i = 1; $i <= $count; $i++) {
            $sequence = $existingCount + $i;
            $randomDigits = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            
            $name = "QRTPersonnel " . $sequence;
            $email = "qrtpersonnel" . $sequence . "@qrtms.com"; 
            $password = "qrt" . $monthYear . $randomDigits;

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'personnel',
                'is_active' => true,
                'hourly_rate' => $rate,
                'phone_number' => null,
                'address' => null,
            ]);

            $generatedAccounts[] = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ];
        }

        return redirect()->route('admin.personnels.index')
            ->with('success', "Successfully created {$count} personnel accounts.")
            ->with('generated_accounts', $generatedAccounts);
    }

    /**
     * Export the generated personnel accounts to a CSV file.
     */
    public function exportGeneratedAccounts()
    {
        if (!session()->has('generated_accounts')) {
            return redirect()->route('admin.personnels.index')->with('error', 'No generated accounts found to export. Please create a batch first.');
        }

        return Excel::download(new GeneratedPersonnelExport, 'personnel_accounts_' . now()->format('Ymd_His') . '.xlsx');
    }
}

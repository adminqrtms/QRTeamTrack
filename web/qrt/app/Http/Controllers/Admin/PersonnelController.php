<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Location;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Support\PhAddress;
use Carbon\Carbon;
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

        // Open duty sessions, so the list can show who is on duty right now
        $onDuty = Attendance::with('location')
            ->whereIn('user_id', $personnel->pluck('id'))
            ->whereNull('time_out')
            ->get()
            ->keyBy('user_id');

        return view('admin.personnels.index', compact('personnel', 'onDuty'));
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
    /** Highest hourly rate (PHP) the admin can set for a personnel. */
    public const MAX_HOURLY_RATE = 100;

    /**
     * Rules shared by Add and Edit Personnel.
     * The password is required when adding, optional when editing.
     */
    private function personnelRules(?User $personnel = null): array
    {
        $namePart = ['string', 'max:100', "regex:/^[\\pL .'-]+$/u"];

        return [
            'first_name' => array_merge(['required'], $namePart),
            'middle_name' => array_merge(['nullable'], $namePart),
            'last_name' => array_merge(['required'], $namePart),
            'email' => ['required', 'string', 'email', 'max:255',
                $personnel ? Rule::unique('users')->ignore($personnel->id) : Rule::unique('users')],
            'phone_number' => ['required', 'regex:/^09\\d{9}$/'],
            'street' => 'required|string|max:255',
            'province' => 'required|string|max:150',
            'city' => 'required|string|max:150',
            'barangay' => ['required', 'string', 'max:150', function ($attribute, $value, $fail) {
                if (!PhAddress::exists(request('province'), request('city'), $value)) {
                    $fail('Choose the province, city and barangay from the lists.');
                }
            }],
            'hourly_rate' => 'required|numeric|min:0|max:' . self::MAX_HOURLY_RATE,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => [$personnel ? 'nullable' : 'required', 'string', 'min:8',
                'regex:/[A-Z]/', 'regex:/[^A-Za-z0-9]/', 'confirmed'],
        ];
    }

    private function personnelMessages(): array
    {
        return [
            'first_name.regex' => 'The first name may only contain letters.',
            'middle_name.regex' => 'The middle name may only contain letters.',
            'last_name.regex' => 'The last name may only contain letters.',
            'phone_number.regex' => 'The phone number must be exactly 11 digits starting with 09 (e.g. 09171234567).',
            'hourly_rate.max' => 'The hourly rate may not be more than ₱' . self::MAX_HOURLY_RATE . '.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.regex' => 'The password needs at least 1 capital letter and 1 symbol (e.g. ! @ # $).',
        ];
    }

    /** Fields saved from the Add/Edit Personnel form. */
    private function personnelData(Request $request): array
    {
        $trim = fn ($key) => ($v = trim((string) $request->input($key))) === '' ? null : preg_replace('/\s+/', ' ', $v);

        return [
            'first_name' => $trim('first_name'),
            'middle_name' => $trim('middle_name'),
            'last_name' => $trim('last_name'),
            'email' => strtolower(trim($request->email)),
            'phone_number' => $request->phone_number,
            'street' => $trim('street'),
            'barangay' => $request->barangay,
            'city' => $request->city,
            'province' => $request->province,
            'address' => PhAddress::format($trim('street'), $request->barangay, $request->city, $request->province),
            'hourly_rate' => $request->hourly_rate,
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->personnelRules(), $this->personnelMessages());

        $data = $this->personnelData($request) + [
            'password' => Hash::make($request->password),
            'role' => 'personnel', // Force role to personnel
            'is_active' => true,
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

        $request->validate($this->personnelRules($personnel), $this->personnelMessages());

        $personnel->fill($this->personnelData($request));

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
    /**
     * End a personnel's duty session from the admin panel, e.g. when they
     * left without tapping TIME OUT in the app.
     */
    public function forceTimeOut(Request $request, $id)
    {
        $personnel = User::where('role', 'personnel')->findOrFail($id);

        $attendance = Attendance::with('location')
            ->where('user_id', $personnel->id)
            ->whereNull('time_out')
            ->first();

        if (!$attendance) {
            return back()->with('success', "{$personnel->name} is not on duty.");
        }

        $now = Carbon::now()->setTimezone('Asia/Manila')->startOfMinute();
        $attendance->timeOut($now);

        $station = $attendance->location->location_name ?? 'N/A';
        AuditLog::create([
            'user_id' => $personnel->id,
            'attendance_id' => $attendance->id,
            'location_id' => $attendance->location_id,
            'action' => AuditLog::ACTION_TIME_OUT,
            'description' => "Timed out by admin {$request->user()->name} from {$station} ({$attendance->hours_worked} hrs worked)",
            'ip_address' => $request->ip(),
            'logged_at' => $now,
        ]);

        return back()->with('success', "{$personnel->name} has been timed out from {$station} ({$attendance->hours_worked} hrs worked).");
    }

    public function toggleStatus($id)
    {
        $personnel = User::findOrFail($id);
        $personnel->is_active = !$personnel->is_active;
        $personnel->save();

        // A deactivated account is signed out of the mobile app right away
        if (!$personnel->is_active) {
            $personnel->tokens()->delete();
        }

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

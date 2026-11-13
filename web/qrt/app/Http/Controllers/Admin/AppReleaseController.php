<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;

/**
 * Admin page listing the app versions phones can update to.
 * New versions are published by build-apk.bat on this PC.
 */
class AppReleaseController extends Controller
{
    public function index()
    {
        $releases = AppRelease::orderByDesc('version_code')->get();

        return view('admin.app_releases.index', compact('releases'));
    }

    public function update(Request $request, AppRelease $release)
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:5000',
            'required' => 'nullable|boolean',
        ]);

        $release->update([
            'notes' => $data['notes'] ?? null,
            'required' => $request->boolean('required'),
        ]);

        return back()->with('success', "Version {$release->version_name} updated.");
    }

    public function destroy(AppRelease $release)
    {
        $release->delete();

        return back()->with('success', "Version {$release->version_name} deleted. Phones will now be offered the newest remaining version.");
    }
}

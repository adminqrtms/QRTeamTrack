<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * In-app updates: the app asks for the newest version on start and
 * downloads it from here. Public, so the login screen can update too.
 */
class AppUpdateController extends Controller
{
    public function latest(Request $request)
    {
        $release = AppRelease::newest();

        if (!$release) {
            return response()->json(['data' => null]);
        }

        // An older required version the phone hasn't installed makes this one required too
        $installed = (int) $request->query('current', 0);
        $required = $release->required || AppRelease::where('required', true)
            ->where('version_code', '>', $installed)
            ->exists();

        return response()->json(['data' => [
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'changes' => $release->changeList(),
            'required' => $required,
            'size' => $release->apk_size,
            // Relative: the app adds its own server address (Wi-Fi IP or ngrok)
            'download_path' => "/api/app/releases/{$release->id}/download",
            'released_at' => $release->created_at,
        ]]);
    }

    public function download($id)
    {
        $release = AppRelease::find($id);
        $disk = Storage::disk('local');

        if (!$release || !$disk->exists($release->apk_path)) {
            return response()->json(['message' => 'Update file not found'], 404);
        }

        return response()->download(
            $disk->path($release->apk_path),
            "QRTeamTrack-{$release->version_name}.apk",
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use App\Support\AppDownload;

/**
 * Public page for installing the app: newest version, Download button,
 * install steps and a QR code other phones can scan.
 */
class AppDownloadController extends Controller
{
    public function show()
    {
        $release = AppRelease::newest();
        $pageUrl = AppDownload::pageUrl();

        return view('download', [
            'release' => $release,
            'pageUrl' => $pageUrl,
            'qr' => AppDownload::qrSvg($pageUrl),
        ]);
    }

    /** Short link straight to the newest APK file. */
    public function latestApk()
    {
        $release = AppRelease::newest();

        if (!$release) {
            return redirect('/download');
        }

        return redirect("/api/app/releases/{$release->id}/download");
    }
}

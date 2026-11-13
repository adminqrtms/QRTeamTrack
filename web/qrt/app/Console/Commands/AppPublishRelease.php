<?php

namespace App\Console\Commands;

use App\Models\AppRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes a built APK as an in-app update (used by build-apk.bat).
 */
class AppPublishRelease extends Command
{
    protected $signature = 'qrt:app-publish
        {apk : Path to the built APK}
        {--code= : Build number the APK was built with}
        {--name= : Version shown to users, e.g. 1.0.5}
        {--notes-file= : Text file with the changes, one per line}
        {--required : Users must install this update}';

    protected $description = 'Publish a built APK as an in-app update';

    public function handle(): int
    {
        $apk = $this->argument('apk');
        $code = (int) $this->option('code');
        $name = trim((string) $this->option('name'));

        if (!is_file($apk)) {
            $this->error("APK not found: {$apk}");
            return self::FAILURE;
        }
        if ($code < 1 || $name === '') {
            $this->error('Give the build number (--code) and version (--name).');
            return self::FAILURE;
        }
        if (AppRelease::where('version_code', '>=', $code)->exists()) {
            $this->error("A version with build number {$code} or higher is already published. Build again to get a new number.");
            return self::FAILURE;
        }

        $notesFile = $this->option('notes-file');
        $notes = $notesFile && is_file($notesFile) ? trim(file_get_contents($notesFile)) : '';

        $path = "releases/QRTeamTrack-{$name}-{$code}.apk";
        $stream = fopen($apk, 'rb');
        Storage::disk('local')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $release = AppRelease::create([
            'version_code' => $code,
            'version_name' => $name,
            'notes' => $notes !== '' ? $notes : null,
            'required' => (bool) $this->option('required'),
            'apk_path' => $path,
            'apk_size' => filesize($apk),
        ]);

        $this->info("Published version {$release->version_name} (build {$release->version_code}, {$release->sizeLabel()}).");
        $this->line('Installed apps will offer this update the next time they open.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AppRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Prints the build number for the next app version (used by build-apk.bat).
 * Installed apps from before in-app updates are build 1, so this starts at 2.
 */
class AppNextVersion extends Command
{
    protected $signature = 'qrt:app-next-version';

    protected $description = 'Print the next app build number (for build-apk.bat)';

    public function handle(): int
    {
        $latest = Schema::hasTable('app_releases')
            ? (int) AppRelease::max('version_code')
            : 0;

        $this->line((string) max(2, $latest + 1));

        return self::SUCCESS;
    }
}

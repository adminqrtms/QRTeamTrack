<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A version of the mobile app that installed apps can update to.
 * The APK lives on the private 'local' disk under releases/.
 */
class AppRelease extends Model
{
    protected $fillable = [
        'version_code',
        'version_name',
        'notes',
        'required',
        'apk_path',
        'apk_size',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'required' => 'boolean',
        'apk_size' => 'integer',
    ];

    public static function newest(): ?self
    {
        return static::orderByDesc('version_code')->first();
    }

    /** "What's new" as a list of lines. */
    public function changeList(): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim(ltrim(trim($line), "-*\u{2022} ")),
            preg_split('/\r\n|\r|\n/', (string) $this->notes)
        )));
    }

    public function sizeLabel(): string
    {
        return number_format($this->apk_size / 1048576, 1) . ' MB';
    }

    protected static function booted(): void
    {
        static::deleted(function (AppRelease $release) {
            Storage::disk('local')->delete($release->apk_path);
        });
    }
}

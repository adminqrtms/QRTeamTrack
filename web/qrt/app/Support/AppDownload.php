<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The public "download the app" link and its QR code.
 */
class AppDownload
{
    /**
     * Address phones can reach: APP_PUBLIC_URL, else the ngrok domain saved by
     * setup-ngrok.bat, else the address this page was opened with.
     */
    public static function baseUrl(): string
    {
        $configured = config('services.app_download.public_url');
        if ($configured) {
            return rtrim($configured, '/');
        }

        $domainFile = base_path('../../tools/ngrok/domain.txt');
        if (is_file($domainFile)) {
            $domain = trim((string) file_get_contents($domainFile));
            if ($domain !== '') {
                return 'https://' . $domain;
            }
        }

        return rtrim(request()->root(), '/');
    }

    public static function pageUrl(): string
    {
        return self::baseUrl() . '/download';
    }

    /** True when the link only works on this PC (no ngrok / public address). */
    public static function isLocalOnly(): bool
    {
        $host = parse_url(self::baseUrl(), PHP_URL_HOST) ?: '';

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /** Inline SVG QR code for $text. */
    public static function qrSvg(string $text, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));
        $svg = $writer->writeString($text);

        // Drop the XML declaration so it can sit inside HTML
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}

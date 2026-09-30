<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Creates LiveKit access tokens (a JWT signed with the LiveKit API secret)
 * that let one user join one call room.
 */
class LiveKitService
{
    /**
     * The LiveKit server address the app should connect to. Uses LIVEKIT_URL
     * when set; otherwise the same host the app used to reach this API, on
     * LiveKit's default port (works for a local server on the same Wi-Fi).
     */
    public function url(Request $request): string
    {
        $configured = config('services.livekit.url');
        if ($configured) {
            return $configured;
        }

        return 'ws://' . $request->getHost() . ':7880';
    }

    public function token(string $room, string $identity, string $name, int $ttlSeconds = 7200): string
    {
        $now = time();

        $claims = [
            'iss' => config('services.livekit.key'),
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        $segments = [
            $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($claims)),
        ];
        $segments[] = $this->base64Url(
            hash_hmac('sha256', implode('.', $segments), config('services.livekit.secret'), true)
        );

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

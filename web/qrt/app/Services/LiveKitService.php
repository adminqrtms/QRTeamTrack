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
        return $this->sign([
            'sub' => $identity,
            'name' => $name,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ], $ttlSeconds);
    }

    /**
     * Checks the LiveKit address, key and secret by asking the server to
     * list its rooms. Returns [ok, message].
     */
    public function check(string $url): array
    {
        $httpUrl = preg_replace('#^ws(s?)://#', 'http$1://', rtrim($url, '/'));
        $token = $this->sign(['sub' => 'qrt-check', 'video' => ['roomList' => true]], 60);

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(10)
                ->post($httpUrl . '/twirp/livekit.RoomService/ListRooms', (object) []);
        } catch (\Throwable $e) {
            return [false, "Can't reach {$httpUrl}: " . $e->getMessage()];
        }

        if ($response->successful()) {
            return [true, 'LiveKit accepted the key and secret (' . count($response->json('rooms') ?? []) . ' active rooms).'];
        }
        if (in_array($response->status(), [401, 403], true)) {
            return [false, 'LiveKit rejected the API key/secret (HTTP ' . $response->status() . '). Check LIVEKIT_API_KEY and LIVEKIT_API_SECRET: same project, no spaces or quotes. ' . $response->body()];
        }

        return [false, 'Unexpected answer from LiveKit (HTTP ' . $response->status() . '): ' . $response->body()];
    }

    private function sign(array $claims, int $ttlSeconds): string
    {
        $now = time();
        $claims = [
            'iss' => config('services.livekit.key'),
            // A minute of leeway in case this PC's clock is slightly ahead
            'nbf' => $now - 60,
            'exp' => $now + $ttlSeconds,
        ] + $claims;

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

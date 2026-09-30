<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through Firebase Cloud Messaging (HTTP v1 API).
 *
 * Needs the service account key file from the Firebase console
 * (config('services.fcm.credentials')). Without it, nothing is sent and the
 * rest of the app keeps working normally.
 */
class PushNotificationService
{
    public const CHANNEL_CHAT = 'chat_channel';
    public const CHANNEL_EMERGENCY = 'emergency_channel';

    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Queue a notification to be sent after the HTTP response has been
     * returned, so the app never waits on Firebase.
     */
    public function sendToUsersAfterResponse(
        iterable $userIds,
        string $title,
        string $body,
        array $data = [],
        string $channel = self::CHANNEL_CHAT,
        bool $fullScreen = false,
    ): void {
        if (!$this->isConfigured()) {
            return;
        }

        $userIds = collect($userIds)->unique()->values()->all();
        if (empty($userIds)) {
            return;
        }

        dispatch(function () use ($userIds, $title, $body, $data, $channel, $fullScreen) {
            app(self::class)->sendToUsers($userIds, $title, $body, $data, $channel, $fullScreen);
        })->afterResponse();
    }

    /**
     * Send a notification to every registered phone of the given users.
     *
     * With $fullScreen, the app itself shows the notification as a
     * full-screen alert (like an incoming call), even over the lock screen.
     */
    public function sendToUsers(
        iterable $userIds,
        string $title,
        string $body,
        array $data = [],
        string $channel = self::CHANNEL_CHAT,
        bool $fullScreen = false,
    ): void {
        if (!$this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', collect($userIds)->all())->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data, $channel, $fullScreen);
        }
    }

    private function sendToToken(string $token, string $title, string $body, array $data, string $channel, bool $fullScreen): void
    {
        try {
            $accessToken = $this->accessToken();
            $projectId = $this->credentials()['project_id'];

            if ($fullScreen) {
                // Data-only: Android hands it to the app (even when closed), which
                // shows the full-screen alert itself.
                $message = [
                    'token' => $token,
                    'data' => array_map('strval', $data + [
                        'title' => $title,
                        'body' => $body,
                        'full_screen' => '1',
                    ]),
                    'android' => ['priority' => 'high'],
                ];
            } else {
                $message = $this->notificationMessage($token, $title, $body, $data, $channel);
            }

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => $message,
                ]);

            if ($response->status() === 404) {
                // The app was uninstalled or the token expired.
                DeviceToken::where('token', $token)->delete();
            } elseif ($response->failed()) {
                Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * A regular notification, shown by Android itself.
     */
    private function notificationMessage(string $token, string $title, string $body, array $data, string $channel): array
    {
        $androidNotification = [
            'channel_id' => $channel,
            'sound' => 'default',
        ];
        if (isset($data['tag'])) {
            // Newer notifications with the same tag replace older ones.
            $androidNotification['tag'] = $data['tag'];
        }
        if ($channel === self::CHANNEL_EMERGENCY) {
            $androidNotification['notification_priority'] = 'PRIORITY_MAX';
        }

        return [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            // FCM data values must be strings.
            'data' => array_map('strval', $data),
            'android' => [
                'priority' => 'high',
                'notification' => $androidNotification,
            ],
        ];
    }

    /**
     * OAuth access token for FCM, signed with the service account key and
     * cached until shortly before it expires.
     */
    private function accessToken(): string
    {
        $credentials = $this->credentials();

        return Cache::remember('fcm_access_token_' . md5($credentials['client_email']), 3300, function () use ($credentials) {
            $now = time();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            $signature = '';
            if (!openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign the Firebase access token request.');
            }
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            if ($response->failed() || !$response->json('access_token')) {
                throw new \RuntimeException('Firebase access token request failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = config('services.fcm.credentials');
        if (!$path || !is_file($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            Log::warning('Firebase credentials file is not a valid service account key.', ['path' => $path]);

            return null;
        }

        return $this->credentials = $json;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

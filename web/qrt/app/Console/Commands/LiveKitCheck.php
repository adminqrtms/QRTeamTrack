<?php

namespace App\Console\Commands;

use App\Services\LiveKitService;
use Illuminate\Console\Command;

class LiveKitCheck extends Command
{
    protected $signature = 'livekit:check {--url= : LiveKit address to test (defaults to LIVEKIT_URL, or the local server)}';

    protected $description = 'Check that the LiveKit call server is reachable and accepts the API key and secret';

    public function handle(LiveKitService $liveKit): int
    {
        $url = $this->option('url') ?: config('services.livekit.url') ?: 'ws://127.0.0.1:7880';
        $key = (string) config('services.livekit.key');

        $this->line("LiveKit URL: {$url}");
        $secret = (string) config('services.livekit.secret');

        $this->line('API key:     ' . ($key === '' ? '(empty)' : substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 4))));
        // Never print the secret itself, only what helps spot a bad copy/paste
        $this->line('API secret:  ' . ($secret === '' ? '(empty)' : strlen($secret) . ' characters, ending in "' . substr($secret, -2) . '"'));
        if (preg_match('/\s|["\']/', $key . $secret)) {
            $this->warn('The key or secret contains spaces or quotes. Remove them in .env.');
        }

        if (!preg_match('#^wss?://#', $url)) {
            $this->error('LIVEKIT_URL must start with wss:// (LiveKit Cloud) or ws:// (local server).');

            return self::FAILURE;
        }

        [$ok, $message] = $liveKit->check($url);
        $ok ? $this->info($message) : $this->error($message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

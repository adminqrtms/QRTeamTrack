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
        $this->line('API key:     ' . ($key === '' ? '(empty)' : substr($key, 0, 4) . str_repeat('*', max(0, strlen($key) - 4))));

        if (!preg_match('#^wss?://#', $url)) {
            $this->error('LIVEKIT_URL must start with wss:// (LiveKit Cloud) or ws:// (local server).');

            return self::FAILURE;
        }

        [$ok, $message] = $liveKit->check($url);
        $ok ? $this->info($message) : $this->error($message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

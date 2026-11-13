<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\SocketServer;

/**
 * One local port for the ngrok tunnel: chat WebSockets (/app/...) go to
 * Reverb, everything else to the Laravel server. This lets the app reach
 * both through a single public address on any Wi-Fi or mobile data.
 */
class Gateway extends Command
{
    protected $signature = 'qrt:gateway
        {--port=8090 : Port ngrok connects to}
        {--api=127.0.0.1:8000 : Laravel server}
        {--reverb=127.0.0.1:8080 : Reverb chat server}';

    protected $description = 'Forward one port to the Laravel API and the Reverb chat server (for ngrok)';

    /** Longest request start we wait for before giving up on a client. */
    private const MAX_HEADER = 16384;

    public function handle(): int
    {
        $listen = '127.0.0.1:' . (int) $this->option('port');
        $api = $this->option('api');
        $reverb = $this->option('reverb');

        $server = new SocketServer($listen);
        $connector = new Connector(['timeout' => 10]);

        $server->on('connection', function (ConnectionInterface $client) use ($connector, $api, $reverb) {
            $buffer = '';

            $onData = function ($data) use (&$buffer, &$onData, $client, $connector, $api, $reverb) {
                $buffer .= $data;

                // Wait for the request line ("GET /path HTTP/1.1")
                $lineEnd = strpos($buffer, "\r\n");
                if ($lineEnd === false) {
                    if (strlen($buffer) > self::MAX_HEADER) {
                        $client->close();
                    }
                    return;
                }

                $client->removeListener('data', $onData);
                $client->pause();

                $path = explode(' ', substr($buffer, 0, $lineEnd))[1] ?? '/';
                $target = str_starts_with($path, '/app/') ? $reverb : $api;

                $connector->connect($target)->then(
                    function (ConnectionInterface $upstream) use (&$buffer, $client) {
                        $upstream->write($buffer);
                        $buffer = '';
                        $client->pipe($upstream);
                        $upstream->pipe($client);
                        // end(), not close(): finish sending what is still buffered first
                        $client->on('close', fn () => $upstream->end());
                        $upstream->on('close', fn () => $client->end());
                        $client->resume();
                    },
                    function () use ($client, $target) {
                        $this->warn("Can't reach {$target} - is it running?");
                        $client->end("HTTP/1.1 502 Bad Gateway\r\nContent-Type: text/plain\r\nContent-Length: 29\r\nConnection: close\r\n\r\nQRT server is not running yet");
                    }
                );
            };

            $client->on('data', $onData);
            $client->on('error', fn () => $client->close());
        });

        $this->info("Gateway on http://{$listen}");
        $this->line("  /app/... (chat WebSocket) -> {$reverb}");
        $this->line("  everything else           -> {$api}");

        return self::SUCCESS; // the event loop keeps running until Ctrl+C
    }
}

<?php

namespace App\Realtime;

use Vayu\Realtime\MessageHandler;
use Vayu\Realtime\WebSocketServer;

/**
 * Example real-time handler: a minimal multi-room chat.
 *
 * Configured via config('websocket.handler'). Clients:
 *   1. {"action":"subscribe","channel":"room.lobby"}
 *   2. {"action":"chat.message","channel":"room.lobby","data":{"text":"hi"}}
 * and receive {"event":"chat.message","channel":"room.lobby","data":{...}}.
 */
class ChatHandler implements MessageHandler
{
    public function register(WebSocketServer $server): void
    {
        // Only public rooms / presence channels may be joined here. A real app
        // would verify a token (e.g. a Bearer passed on the first frame).
        $server->authorizeUsing(static function (object $conn, string $channel): bool {
            return str_starts_with($channel, 'room.') || str_starts_with($channel, 'presence.');
        });

        $server->onDisconnected(function (object $conn, WebSocketServer $server): void {
            $id = $server->connectionId($conn);
            foreach ($server->channels()->channelsFor($id) as $channel) {
                $server->broadcast($channel, 'presence.left', ['id' => $id], $id);
            }
        });

        $server->on('chat.message', function (object $conn, array $msg, WebSocketServer $server): void {
            $id = $server->connectionId($conn);
            $channel = trim((string) ($msg['channel'] ?? ''));

            if ($channel === '' || !$server->channels()->inChannel($id, $channel)) {
                $server->sendTo($id, 'error', ['message' => 'Join the room before sending.']);

                return;
            }

            $text = trim((string) (($msg['data'] ?? [])['text'] ?? ''));
            if ($text === '') {
                return;
            }

            $server->broadcast($channel, 'chat.message', [
                'id' => $id,
                'text' => mb_substr($text, 0, 2000),
                'at' => date('c'),
            ]);
        });
    }
}

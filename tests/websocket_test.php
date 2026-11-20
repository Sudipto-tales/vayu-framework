<?php

use App\Realtime\ChatHandler;
use Vayu\Realtime\BroadcastsEvents;
use Vayu\Realtime\Broadcaster;
use Vayu\Realtime\ChannelManager;
use Vayu\Realtime\MessageHandler;
use Vayu\Realtime\WebSocketServer;

return function (Assert $assert): void {
    /* ---- ChannelManager ---- */
    $cm = new ChannelManager();
    $cm->subscribe('1', 'room.a');
    $cm->subscribe('2', 'room.a');
    $cm->subscribe('1', 'room.b');

    $assert->equals('channel members', ['1', '2'], $cm->connections('room.a'));
    $assert->equals('channel count', 2, $cm->channelCount('room.a'));
    $assert->that('inChannel true', $cm->inChannel('1', 'room.b'));
    $assert->that('inChannel false', !$cm->inChannel('2', 'room.b'));
    $assert->equals('channelsFor', ['room.a', 'room.b'], $cm->channelsFor('1'));

    $cm->unsubscribe('2', 'room.a');
    $assert->equals('after unsubscribe', ['1'], $cm->connections('room.a'));

    $left = $cm->forget('1');
    sort($left);
    $assert->equals('forget returns channels left', ['room.a', 'room.b'], $left);
    $assert->equals('empty channel pruned', 0, $cm->channelCount('room.a'));
    $assert->equals('summary empty', [], $cm->summary());

    /* ---- Broadcaster payloads (captured, no socket) ---- */
    $captured = [];
    $b = (new Broadcaster())->setTransport(function (array $payload) use (&$captured): bool {
        $captured[] = $payload;
        return true;
    });

    $assert->that('event() returns transport result', $b->event('room.1', 'chat.message', ['text' => 'hi'], 'self'));
    $assert->equals('broadcast type', WebSocketServer::TYPE_BROADCAST, $captured[0]['type']);
    $assert->equals('broadcast channel', 'room.1', $captured[0]['channel']);
    $assert->equals('broadcast event', 'chat.message', $captured[0]['event']);
    $assert->equals('broadcast data', ['text' => 'hi'], $captured[0]['data']);
    $assert->equals('broadcast except', 'self', $captured[0]['except']);

    $b->to('42', 'notice', ['msg' => 'yo']);
    $assert->equals('direct type', WebSocketServer::TYPE_DIRECT, $captured[1]['type']);
    $assert->equals('direct id', '42', $captured[1]['id']);

    // Line protocol is newline-terminated single-line JSON.
    $line = '';
    (new Broadcaster())->setTransport(function (array $p, string $l) use (&$line): bool { $line = $l; return true; })
        ->event('c', 'e', ['a' => 1]);
    $assert->that('push line ends with newline', str_ends_with($line, "\n"));
    $assert->that('push line has no interior newline', substr_count($line, "\n") === 1);
    $assert->equals('push line decodes', 'c', json_decode(trim($line), true)['channel']);

    /* ---- BroadcastsEvents dispatch fans out over channels ---- */
    $event = new class implements BroadcastsEvents {
        public function broadcastOn(): string|array { return ['room.x', 'room.y']; }
        public function broadcastAs(): string { return 'thing.happened'; }
        public function broadcastWith(): array { return ['ok' => true]; }
    };
    $seen = [];
    (new Broadcaster())->setTransport(function (array $p) use (&$seen): bool { $seen[] = $p['channel']; return true; })
        ->dispatch($event);
    $assert->equals('dispatch hits every channel', ['room.x', 'room.y'], $seen);

    /* ---- Unreachable server is a silent false, never an exception ---- */
    $miss = (new Broadcaster('127.0.0.1', 1, 0.05))->event('room.z', 'e', []);
    $assert->that('unreachable broadcast returns false', $miss === false);

    /* ---- Demo handler conforms to the contract ---- */
    $assert->that('ChatHandler is a MessageHandler', (new ChatHandler()) instanceof MessageHandler);

    /* ---- Auto-broadcast: wildcard listener + Broadcaster (as Application wires it) ---- */
    $pushes = [];
    $bc = (new Broadcaster())->setTransport(function (array $p) use (&$pushes): bool {
        $pushes[] = $p;
        return true;
    });
    $dispatcher = new \Vayu\Events\Dispatcher();
    $dispatcher->listen('*', function (...$args) use ($bc): void {
        $event = $args[0] ?? null;
        if ($event instanceof BroadcastsEvents) {
            $bc->dispatch($event);
        }
    });

    // A BroadcastsEvents object is auto-pushed on its channel/event/payload.
    $dispatcher->dispatch(new \App\Events\UserRegistered(['id' => 7, 'name' => 'Ada', 'password' => 'secret']));
    $assert->equals('auto-broadcast fires once', 1, count($pushes));
    $assert->equals('auto-broadcast channel', 'users', $pushes[0]['channel']);
    $assert->equals('auto-broadcast event name', 'user.registered', $pushes[0]['event']);
    $assert->equals('auto-broadcast carries public fields', 'Ada', $pushes[0]['data']['name']);
    $assert->that('auto-broadcast omits secrets', !array_key_exists('password', $pushes[0]['data']));

    // A plain (non-broadcastable) event is NOT pushed.
    $dispatcher->dispatch('some.event', ['payload']);
    $assert->equals('plain events are not broadcast', 1, count($pushes));
};

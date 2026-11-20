# Real-time (WebSockets)

Vayu ships an optional real-time layer built on [Workerman](https://github.com/walkor/workerman).
It runs as a separate long-lived process; your ordinary HTTP controllers push
messages to connected clients through a loopback **broadcast bridge**, so the
web SAPI never needs Workerman loaded.

```
  Browser ──ws──▶  vayu websocket process ──┐
                     ├─ websocket://0.0.0.0:8080   (clients)
                     └─ text://127.0.0.1:2207      (broadcast bridge, loopback)
                                   ▲
  HTTP controller ──broadcast()────┘  (plain TCP, no Workerman in the web process)
```

## Install

Workerman is an optional (dev) dependency; the core framework runs without it.

```bash
composer require workerman/workerman
```

It needs the `pcntl` and `posix` extensions and does not run on Windows.

## Run the server

```bash
php vayu websocket                 # foreground
php vayu websocket start -d        # daemon
php vayu websocket stop|reload|status
php vayu websocket --port=9000     # override host/port
```

Configuration lives in `config/websocket.php` (driven by `WS_*` env vars).
Keep `workers` at `1` unless you move broadcasting onto an external bus
(Redis / workerman-channel): the in-process bridge only reaches connections
owned by worker `#0`.

## Wire protocol

Text frames are JSON.

**Client → server**

```json
{"action":"subscribe","channel":"room.1"}
{"action":"unsubscribe","channel":"room.1"}
{"action":"ping"}
{"action":"chat.message","channel":"room.1","data":{"text":"hi"}}
```

**Server → client**

```json
{"event":"welcome","data":{"id":"7"}}
{"event":"subscribed","data":{"channel":"room.1","count":3}}
{"event":"pong","data":{"t":1732000000000}}
{"event":"chat.message","channel":"room.1","data":{"id":"7","text":"hi","at":"..."}}
{"event":"error","data":{"message":"..."}}
```

`subscribe`, `unsubscribe`, `ping` and `publish` are built in. Everything else
is handled by your application handler.

## Application handler

Point `config('websocket.handler')` at a class implementing
`Vayu\Realtime\MessageHandler`. It is resolved from the container when the
server boots and registers channel authorisation, lifecycle hooks and custom
actions. See `app/Realtime/ChatHandler.php` for a working multi-room chat.

```php
use Vayu\Realtime\MessageHandler;
use Vayu\Realtime\WebSocketServer;

class ChatHandler implements MessageHandler
{
    public function register(WebSocketServer $server): void
    {
        $server->authorizeUsing(fn ($conn, $channel) => str_starts_with($channel, 'room.'));

        $server->on('chat.message', function ($conn, array $msg, WebSocketServer $server) {
            $channel = $msg['channel'] ?? '';
            $server->broadcast($channel, 'chat.message', [
                'id'   => $server->connectionId($conn),
                'text' => (string) ($msg['data']['text'] ?? ''),
                'at'   => date('c'),
            ]);
        });
    }
}
```

## Broadcasting from HTTP

From any controller, command, job or listener:

```php
broadcast('room.1', 'chat.message', ['text' => 'Hello']);   // channel broadcast
broadcast()->to($connectionId, 'notice', ['msg' => 'hi']);   // one connection
broadcast()->dispatch($event);                               // a BroadcastsEvents object
```

A push to a server that is not running is a silent no-op (it never breaks the
HTTP response) and returns `false`. The demo endpoint `POST /api/broadcast`
exercises this path, and `public/realtime.html` is a browser client for it.

## Auto-broadcasting events

Any dispatched event implementing `Vayu\Realtime\BroadcastsEvents` is pushed to
its channel(s) automatically — no explicit `broadcast()` call needed. The
dispatcher runs a wildcard observer (registered in the `events` service) that
forwards broadcastable events to the bridge. It is controlled by
`config('websocket.auto_broadcast')` (`WS_AUTO_BROADCAST`, on by default) and is
skipped when a listener halts the event (returns `false`).

```php
use Vayu\Realtime\BroadcastsEvents;

class UserRegistered implements BroadcastsEvents
{
    public function __construct(public array $user) {}
    public function broadcastOn(): string|array { return 'users'; }
    public function broadcastAs(): string        { return 'user.registered'; }
    public function broadcastWith(): array        { return ['id' => $this->user['id']]; }
}

// Anywhere in the app — runs listeners AND pushes to the "users" channel:
event(new UserRegistered($user));
```

Only put safe, public fields in `broadcastWith()` — never secrets. See
`app/Events/UserRegistered.php` for the working example.

## Try it

```bash
php vayu run                 # terminal 1 — HTTP server on :8000
php vayu websocket           # terminal 2 — WS server on :8080
# open http://localhost:8000/realtime.html in two browser tabs
```

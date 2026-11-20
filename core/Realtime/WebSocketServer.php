<?php

namespace Vayu\Realtime;

use Vayu\Application;

/**
 * A Workerman-backed WebSocket server.
 *
 * Wire protocol (JSON text frames):
 *
 *   client -> server  {"action":"subscribe","channel":"room.1"}
 *                      {"action":"unsubscribe","channel":"room.1"}
 *                      {"action":"ping"}
 *                      {"action":"<custom>","channel":"...","data":{...}}
 *
 *   server -> client  {"event":"welcome","data":{"id":"7"}}
 *                      {"event":"subscribed","data":{"channel":"room.1"}}
 *                      {"event":"pong","data":{"t":<ms>}}
 *                      {"event":"error","data":{"message":"..."}}
 *                      {"event":"<name>","channel":"room.1","data":{...}}
 *
 * Only Workerman-specific code runs inside start(); the rest is plain PHP so
 * the file loads fine on a checkout without Workerman installed.
 */
class WebSocketServer
{
    public const TYPE_BROADCAST = 'broadcast';
    public const TYPE_DIRECT = 'direct';

    protected ChannelManager $channels;

    /** @var array<string, callable(object, array<string,mixed>, self): void> action => handler */
    protected array $handlers = [];

    /** @var (callable(object, self): void)|null */
    protected $connectedHook = null;

    /** @var (callable(object, self): void)|null */
    protected $disconnectedHook = null;

    /** @var (callable(object, string): bool)|null */
    protected $authorizer = null;

    /** @var array<string, object> connection id => Workerman TcpConnection */
    protected array $connections = [];

    /** @var array<string, int> connection id => last-activity unix time */
    protected array $lastActive = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected Application $app,
        protected array $config = [],
    ) {
        $this->channels = new ChannelManager();
        $this->registerDefaultHandlers();
    }

    public function channels(): ChannelManager
    {
        return $this->channels;
    }

    /* ----------------------------------------------------------------
     | Registration API (called by the application MessageHandler)
     | ---------------------------------------------------------------- */

    /**
     * Handle a custom client action (e.g. "chat.message").
     *
     * @param callable(object, array<string,mixed>, self): void $handler
     */
    public function on(string $action, callable $handler): static
    {
        $this->handlers[$action] = $handler;

        return $this;
    }

    /** @param callable(object, self): void $callback */
    public function onConnected(callable $callback): static
    {
        $this->connectedHook = $callback;

        return $this;
    }

    /** @param callable(object, self): void $callback */
    public function onDisconnected(callable $callback): static
    {
        $this->disconnectedHook = $callback;

        return $this;
    }

    /**
     * Decide whether a connection may subscribe to a channel.
     *
     * @param callable(object, string): bool $callback
     */
    public function authorizeUsing(callable $callback): static
    {
        $this->authorizer = $callback;

        return $this;
    }

    /* ----------------------------------------------------------------
     | Sending
     | ---------------------------------------------------------------- */

    /**
     * Send an event to every connection subscribed to a channel.
     *
     * @param array<string, mixed> $data
     * @return int number of clients the frame was sent to
     */
    public function broadcast(string $channel, string $event, array $data = [], ?string $exceptId = null): int
    {
        $frame = $this->frame($event, $data, $channel);
        $sent = 0;

        foreach ($this->channels->connections($channel) as $id) {
            if ($id === $exceptId) {
                continue;
            }
            if (isset($this->connections[$id])) {
                $this->connections[$id]->send($frame);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendTo(string $connectionId, string $event, array $data = [], ?string $channel = null): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        $this->connections[$connectionId]->send($this->frame($event, $data, $channel));

        return true;
    }

    public function connectionId(object $connection): string
    {
        return (string) ($connection->id ?? spl_object_id($connection));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function frame(string $event, array $data = [], ?string $channel = null): string
    {
        $payload = ['event' => $event];
        if ($channel !== null) {
            $payload['channel'] = $channel;
        }
        $payload['data'] = $data;

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /* ----------------------------------------------------------------
     | Built-in client actions
     | ---------------------------------------------------------------- */

    protected function registerDefaultHandlers(): void
    {
        $this->on('subscribe', function (object $conn, array $msg): void {
            $id = $this->connectionId($conn);
            $channel = trim((string) ($msg['channel'] ?? ''));

            if ($channel === '') {
                $this->sendTo($id, 'error', ['message' => 'A channel is required to subscribe.']);

                return;
            }

            if ($this->authorizer !== null && !($this->authorizer)($conn, $channel)) {
                $this->sendTo($id, 'error', ['message' => "Not authorized for channel [{$channel}]."]);

                return;
            }

            $this->channels->subscribe($id, $channel);
            $this->sendTo($id, 'subscribed', ['channel' => $channel, 'count' => $this->channels->channelCount($channel)]);
        });

        $this->on('unsubscribe', function (object $conn, array $msg): void {
            $id = $this->connectionId($conn);
            $channel = trim((string) ($msg['channel'] ?? ''));
            if ($channel !== '') {
                $this->channels->unsubscribe($id, $channel);
                $this->sendTo($id, 'unsubscribed', ['channel' => $channel]);
            }
        });

        $this->on('ping', function (object $conn): void {
            $this->sendTo($this->connectionId($conn), 'pong', ['t' => (int) (microtime(true) * 1000)]);
        });

        $this->on('publish', function (object $conn, array $msg): void {
            if (!($this->config['allow_client_publish'] ?? false)) {
                $this->sendTo($this->connectionId($conn), 'error', ['message' => 'Client publishing is disabled.']);

                return;
            }

            $id = $this->connectionId($conn);
            $channel = trim((string) ($msg['channel'] ?? ''));
            if ($channel === '' || !$this->channels->inChannel($id, $channel)) {
                $this->sendTo($id, 'error', ['message' => 'Subscribe to the channel before publishing.']);

                return;
            }

            $this->broadcast(
                $channel,
                (string) ($msg['event'] ?? 'message'),
                (array) ($msg['data'] ?? []),
                $id
            );
        });
    }

    /* ----------------------------------------------------------------
     | Workerman event callbacks (one process, shared memory)
     | ---------------------------------------------------------------- */

    public function handleConnect(object $connection): void
    {
        $id = $this->connectionId($connection);
        $this->connections[$id] = $connection;
        $this->lastActive[$id] = time();

        $allowed = $this->config['allowed_origins'] ?? [];
        $connection->onWebSocketConnect = function (object $conn, string $header) use ($id, $allowed): void {
            if (!empty($allowed) && !$this->originAllowed($header, (array) $allowed)) {
                $this->sendTo($id, 'error', ['message' => 'Origin not allowed.']);
                $conn->close();

                return;
            }

            $this->sendTo($id, 'welcome', ['id' => $id]);
            if ($this->connectedHook !== null) {
                ($this->connectedHook)($conn, $this);
            }
        };
    }

    public function handleMessage(object $connection, string $raw): void
    {
        $id = $this->connectionId($connection);
        $this->lastActive[$id] = time();

        $msg = json_decode($raw, true);
        if (!is_array($msg)) {
            $this->sendTo($id, 'error', ['message' => 'Malformed message; expected JSON.']);

            return;
        }

        $action = (string) ($msg['action'] ?? ($msg['event'] ?? ''));
        if ($action === '') {
            $this->sendTo($id, 'error', ['message' => 'Missing "action".']);

            return;
        }

        if (!isset($this->handlers[$action])) {
            $this->sendTo($id, 'error', ['message' => "Unknown action [{$action}]."]);

            return;
        }

        try {
            ($this->handlers[$action])($connection, $msg, $this);
        } catch (\Throwable $e) {
            $this->app->logger()->error('WebSocket handler error: ' . $e->getMessage());
            $this->sendTo($id, 'error', ['message' => 'Server error handling the message.']);
        }
    }

    public function handleClose(object $connection): void
    {
        $id = $this->connectionId($connection);

        if ($this->disconnectedHook !== null) {
            ($this->disconnectedHook)($connection, $this);
        }

        $this->channels->forget($id);
        unset($this->connections[$id], $this->lastActive[$id]);
    }

    /**
     * Deliver a message received over the internal broadcast bridge.
     */
    public function handleInternal(string $raw): void
    {
        $msg = json_decode($raw, true);
        if (!is_array($msg)) {
            return;
        }

        $type = (string) ($msg['type'] ?? self::TYPE_BROADCAST);
        $event = (string) ($msg['event'] ?? 'message');
        $data = (array) ($msg['data'] ?? []);

        if ($type === self::TYPE_DIRECT) {
            $this->sendTo((string) ($msg['id'] ?? ''), $event, $data, $msg['channel'] ?? null);

            return;
        }

        $this->broadcast(
            (string) ($msg['channel'] ?? ''),
            $event,
            $data,
            isset($msg['except']) ? (string) $msg['except'] : null
        );
    }

    /**
     * @param list<string> $allowed
     */
    protected function originAllowed(string $header, array $allowed): bool
    {
        if (preg_match('/^Origin:\s*(.+)$/mi', $header, $m) !== 1) {
            return false;
        }

        return in_array(trim($m[1]), $allowed, true);
    }

    /* ----------------------------------------------------------------
     | Boot
     | ---------------------------------------------------------------- */

    protected function bootHandler(): void
    {
        $class = $this->config['handler'] ?? null;
        if (!is_string($class) || $class === '' || !class_exists($class)) {
            return;
        }

        $handler = $this->app->make($class);
        if ($handler instanceof MessageHandler) {
            $handler->register($this);
        }
    }

    /**
     * Build and configure the Workerman worker (listeners, callbacks, the
     * in-process broadcast bridge and heartbeat) without running the event
     * loop. Useful for composing extra workers in the same process or for
     * tests; start() wraps this plus Worker::runAll().
     *
     * @return \Workerman\Worker
     */
    public function createServerWorker(): object
    {
        if (!class_exists(\Workerman\Worker::class)) {
            throw new \RuntimeException(
                'Workerman is not installed. Run: composer require workerman/workerman'
            );
        }

        $this->bootHandler();

        $host = (string) ($this->config['host'] ?? '0.0.0.0');
        $port = (int) ($this->config['port'] ?? 8080);

        $worker = new \Workerman\Worker("websocket://{$host}:{$port}");
        $worker->name = 'vayu-websocket';
        $worker->count = max(1, (int) ($this->config['workers'] ?? 1));

        $worker->onWorkerStart = function (object $w): void {
            // Internal broadcast bridge + heartbeat live in worker #0 only, so
            // they share memory with the connections they operate on.
            if ((int) ($w->id ?? 0) !== 0) {
                return;
            }

            $this->startBroadcastBridge();
            $this->startHeartbeat();
        };

        $worker->onConnect = fn (object $conn) => $this->handleConnect($conn);
        $worker->onMessage = fn (object $conn, $data) => $this->handleMessage($conn, (string) $data);
        $worker->onClose = fn (object $conn) => $this->handleClose($conn);

        return $worker;
    }

    /**
     * Build the Workerman workers and run the event loop. Blocks.
     */
    public function start(): void
    {
        $this->createServerWorker();

        \Workerman\Worker::runAll();
    }

    protected function startBroadcastBridge(): void
    {
        $host = (string) ($this->config['broadcast_host'] ?? '127.0.0.1');
        $port = (int) ($this->config['broadcast_port'] ?? 2207);

        // text:// = newline-delimited frames, matching Broadcaster. Created and
        // listen()'d inside the running worker so it shares this process.
        $bridge = new \Workerman\Worker("text://{$host}:{$port}");
        $bridge->onMessage = fn (object $conn, $data) => $this->handleInternal((string) $data);
        $bridge->listen();
    }

    protected function startHeartbeat(): void
    {
        $interval = (int) ($this->config['heartbeat'] ?? 0);
        if ($interval <= 0 || !class_exists(\Workerman\Timer::class)) {
            return;
        }

        \Workerman\Timer::add($interval, function () use ($interval): void {
            $deadline = time() - ($interval * 2);
            foreach ($this->connections as $id => $conn) {
                if (($this->lastActive[$id] ?? 0) < $deadline) {
                    $conn->close();
                }
            }
        });
    }
}

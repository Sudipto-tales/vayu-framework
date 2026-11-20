<?php

namespace Vayu\Realtime;

/**
 * Pushes messages from the ordinary HTTP process to clients connected to the
 * Workerman WebSocket process.
 *
 * Deliberately dependency-free: it speaks a tiny newline-delimited JSON
 * protocol over a plain TCP socket to the WS process's internal broadcast
 * bridge, so controllers can broadcast without Workerman being loaded in the
 * web SAPI at all. If the WS server is not running the push is a silent no-op
 * (it never breaks the HTTP response) and returns false.
 */
class Broadcaster
{
    /** @var (callable(array<string,mixed>, string): bool)|null */
    protected $transport = null;

    public function __construct(
        protected string $host = '127.0.0.1',
        protected int $port = 2207,
        protected float $timeout = 0.5,
    ) {
    }

    /**
     * Override how a payload is delivered (used by tests to capture pushes
     * instead of opening a socket).
     *
     * @param (callable(array<string,mixed>, string): bool)|null $transport
     */
    public function setTransport(?callable $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * Broadcast an event to everyone subscribed to a channel.
     *
     * @param array<string, mixed> $data
     * @param string|null          $except connection id to skip (e.g. the sender)
     */
    public function event(string $channel, string $event, array $data = [], ?string $except = null): bool
    {
        return $this->push([
            'type' => WebSocketServer::TYPE_BROADCAST,
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'except' => $except,
        ]);
    }

    /**
     * Send an event to a single connection by id.
     *
     * @param array<string, mixed> $data
     */
    public function to(string $connectionId, string $event, array $data = [], ?string $channel = null): bool
    {
        return $this->push([
            'type' => WebSocketServer::TYPE_DIRECT,
            'id' => $connectionId,
            'event' => $event,
            'data' => $data,
            'channel' => $channel,
        ]);
    }

    /**
     * Broadcast a BroadcastsEvents object to all of its channels.
     */
    public function dispatch(BroadcastsEvents $event): bool
    {
        $ok = true;
        foreach ((array) $event->broadcastOn() as $channel) {
            $ok = $this->event((string) $channel, $event->broadcastAs(), $event->broadcastWith()) && $ok;
        }

        return $ok;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function push(array $payload): bool
    {
        $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        if ($this->transport !== null) {
            return (bool) ($this->transport)($payload, $line);
        }

        return $this->sendOverSocket($line);
    }

    protected function sendOverSocket(string $line): bool
    {
        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($client === false) {
            return false;
        }

        $seconds = (int) $this->timeout;
        stream_set_timeout($client, $seconds, (int) (($this->timeout - $seconds) * 1_000_000));

        $written = @fwrite($client, $line);
        @fclose($client);

        return $written !== false && $written > 0;
    }
}

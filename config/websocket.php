<?php

/**
 * Real-time / WebSocket configuration (Workerman).
 *
 * The server is started with `php vayu websocket`. A controller running in the
 * ordinary HTTP process pushes messages to connected clients through the
 * internal "broadcast bridge" (a loopback listener owned by the WS process);
 * see Vayu\Realtime\Broadcaster. The bridge MUST stay bound to loopback.
 */
return [
    // Public WebSocket endpoint clients connect to (ws://host:port).
    'host' => env('WS_HOST', '0.0.0.0'),
    'port' => (int) env('WS_PORT', 8080),

    // Worker processes. Keep at 1 unless you move broadcasting to an external
    // bus (Redis / workerman-channel): the in-process broadcast bridge only
    // reaches connections owned by worker #0.
    'workers' => (int) env('WS_WORKERS', 1),

    // Optional Origin allow-list for the handshake (empty = allow all).
    // Comma-separated, e.g. "http://localhost:8000,https://app.example.com".
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WS_ALLOWED_ORIGINS', ''))
    ))),

    // Auto-broadcast any dispatched event implementing
    // Vayu\Realtime\BroadcastsEvents to its channel(s). Off => broadcast
    // explicitly with broadcast()->dispatch($event).
    'auto_broadcast' => filter_var(env('WS_AUTO_BROADCAST', true), FILTER_VALIDATE_BOOLEAN),

    // Let clients publish straight to a channel they are subscribed to without
    // going through a server-side handler. Off by default (trust the server).
    'allow_client_publish' => filter_var(env('WS_ALLOW_CLIENT_PUBLISH', false), FILTER_VALIDATE_BOOLEAN),

    // Seconds of silence before a connection is considered dead and closed.
    // 0 disables the heartbeat sweep.
    'heartbeat' => (int) env('WS_HEARTBEAT', 55),

    // Internal broadcast bridge (HTTP process -> WS process). Loopback only.
    'broadcast_host' => env('WS_BROADCAST_HOST', '127.0.0.1'),
    'broadcast_port' => (int) env('WS_BROADCAST_PORT', 2207),
    'broadcast_timeout' => (float) env('WS_BROADCAST_TIMEOUT', 0.5),

    // Application message handler: a class implementing
    // Vayu\Realtime\MessageHandler, resolved from the container and given the
    // server to register its channel/event handlers. null = subscribe/ping only.
    'handler' => \App\Realtime\ChatHandler::class,
];

<?php

namespace Vayu\Realtime;

/**
 * Application entry point for wiring real-time behaviour.
 *
 * The class named by config('websocket.handler') is resolved from the container
 * when the server boots and handed the server so it can register channel
 * authorisation, connect/disconnect hooks and custom client-action handlers.
 */
interface MessageHandler
{
    public function register(WebSocketServer $server): void;
}

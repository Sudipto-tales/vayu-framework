<?php

namespace Vayu\Realtime;

/**
 * Marker for an event object that can be pushed to WebSocket clients.
 *
 * Dispatch it with Broadcaster::dispatch() (or broadcast()->dispatch($event)):
 *
 *     class MessagePosted implements BroadcastsEvents
 *     {
 *         public function __construct(public array $message) {}
 *         public function broadcastOn(): string|array { return 'room.' . $this->message['room']; }
 *         public function broadcastAs(): string        { return 'chat.message'; }
 *         public function broadcastWith(): array        { return $this->message; }
 *     }
 */
interface BroadcastsEvents
{
    /**
     * Channel name, or a list of channel names, to broadcast on.
     *
     * @return string|list<string>
     */
    public function broadcastOn(): string|array;

    /**
     * The event name clients receive.
     */
    public function broadcastAs(): string;

    /**
     * The payload sent to clients.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array;
}

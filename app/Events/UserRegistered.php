<?php

namespace App\Events;

use Vayu\Realtime\BroadcastsEvents;

/**
 * Fired after a user signs up. It implements BroadcastsEvents, so once the
 * WebSocket server is running, dispatching it (event(new UserRegistered($u)))
 * both runs its listeners AND pushes to the "users" channel automatically.
 */
class UserRegistered implements BroadcastsEvents
{
    /**
     * @param array<string, mixed> $user
     */
    public function __construct(public array $user)
    {
    }

    public function broadcastOn(): string|array
    {
        return 'users';
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }

    /**
     * Only safe, public fields — never broadcast secrets.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->user['id'] ?? null,
            'name' => $this->user['name'] ?? null,
            'at' => date('c'),
        ];
    }
}

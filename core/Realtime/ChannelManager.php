<?php

namespace Vayu\Realtime;

/**
 * Tracks which connections belong to which channels (and vice-versa).
 *
 * Pure in-memory bookkeeping — no Workerman types — so it is trivially unit
 * testable. A "channel" is any string the application gives meaning to
 * (e.g. "room.42", "presence.lobby", "user.7").
 */
class ChannelManager
{
    /** @var array<string, array<string, true>> channel => set of connection ids */
    protected array $channels = [];

    /** @var array<string, array<string, true>> connection id => set of channels */
    protected array $connections = [];

    public function subscribe(string $connectionId, string $channel): void
    {
        $this->channels[$channel][$connectionId] = true;
        $this->connections[$connectionId][$channel] = true;
    }

    public function unsubscribe(string $connectionId, string $channel): void
    {
        unset($this->channels[$channel][$connectionId], $this->connections[$connectionId][$channel]);

        if (empty($this->channels[$channel])) {
            unset($this->channels[$channel]);
        }
        if (empty($this->connections[$connectionId])) {
            unset($this->connections[$connectionId]);
        }
    }

    /**
     * Drop a connection from every channel it was in (on disconnect).
     *
     * @return list<string> the channels it left
     */
    public function forget(string $connectionId): array
    {
        $left = array_keys($this->connections[$connectionId] ?? []);

        foreach ($left as $channel) {
            unset($this->channels[$channel][$connectionId]);
            if (empty($this->channels[$channel])) {
                unset($this->channels[$channel]);
            }
        }

        unset($this->connections[$connectionId]);

        return $left;
    }

    /**
     * @return list<string> connection ids subscribed to the channel
     */
    public function connections(string $channel): array
    {
        // Cast to string: numeric connection ids become int array keys.
        return array_map('strval', array_keys($this->channels[$channel] ?? []));
    }

    /**
     * @return list<string> channels the connection is subscribed to
     */
    public function channelsFor(string $connectionId): array
    {
        return array_keys($this->connections[$connectionId] ?? []);
    }

    public function inChannel(string $connectionId, string $channel): bool
    {
        return isset($this->channels[$channel][$connectionId]);
    }

    public function channelCount(string $channel): int
    {
        return count($this->channels[$channel] ?? []);
    }

    /**
     * @return array<string, int> channel => subscriber count
     */
    public function summary(): array
    {
        return array_map('count', $this->channels);
    }
}

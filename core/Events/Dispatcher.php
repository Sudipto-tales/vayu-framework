<?php

namespace Vayu\Events;

/**
 * Simple event dispatcher.
 *
 *   $events->listen(UserRegistered::class, SendWelcomeEmail::class);
 *   $events->dispatch(new UserRegistered($user));
 *
 * Listeners may be a Closure, a "Class@method" string, or a class name with a
 * handle() method.
 */
class Dispatcher
{
    /** @var array<string, list<callable|string>> */
    protected array $listeners = [];

    /** @var list<callable|string> listeners that observe every event ("*") */
    protected array $wildcards = [];

    /**
     * @param string|list<string>  $events  event name(s), or "*" for all events
     * @param callable|string      $listener
     */
    public function listen(string|array $events, callable|string $listener): void
    {
        foreach ((array) $events as $event) {
            if ($event === '*') {
                $this->wildcards[] = $listener;
                continue;
            }

            $this->listeners[$event][] = $listener;
        }
    }

    public function hasListeners(string $event): bool
    {
        if ($event === '*') {
            return !empty($this->wildcards);
        }

        return !empty($this->listeners[$event]) || !empty($this->wildcards);
    }

    public function forget(string $event): void
    {
        if ($event === '*') {
            $this->wildcards = [];

            return;
        }

        unset($this->listeners[$event]);
    }

    /**
     * Dispatch an event. Pass an event object, or a name + payload.
     *
     * @param array<int, mixed> $payload
     * @return list<mixed> listener return values
     */
    public function dispatch(object|string $event, array $payload = []): array
    {
        $name = is_object($event) ? get_class($event) : $event;
        $arguments = is_object($event) ? [$event] : $payload;

        $results = [];
        $halted = false;
        foreach ($this->listeners[$name] ?? [] as $listener) {
            $result = $this->callListener($listener, $arguments);

            // A listener returning false halts propagation.
            if ($result === false) {
                $halted = true;
                break;
            }

            $results[] = $result;
        }

        // Wildcard observers (e.g. the auto-broadcaster) run after the named
        // listeners, but not for a halted/cancelled event. Their return values
        // never affect the dispatch result or halt propagation.
        if (!$halted) {
            foreach ($this->wildcards as $listener) {
                $this->callListener($listener, $arguments);
            }
        }

        return $results;
    }

    /**
     * @param callable|string  $listener
     * @param array<int, mixed> $arguments
     */
    protected function callListener(callable|string $listener, array $arguments): mixed
    {
        if ($listener instanceof \Closure || (is_array($listener) && is_callable($listener))) {
            return $listener(...$arguments);
        }

        if (is_string($listener)) {
            if (str_contains($listener, '@')) {
                [$class, $method] = explode('@', $listener, 2);

                return (new $class())->{$method}(...$arguments);
            }

            $instance = new $listener();
            if (method_exists($instance, 'handle')) {
                return $instance->handle(...$arguments);
            }

            if (is_callable($instance)) {
                return $instance(...$arguments);
            }
        }

        return null;
    }
}

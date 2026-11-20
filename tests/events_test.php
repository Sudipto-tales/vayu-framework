<?php

use App\Events\UserRegistered;
use Vayu\Events\Dispatcher;

return function (Assert $assert): void {
    $dispatcher = new Dispatcher();

    // Closure listener receiving an event object
    $seen = null;
    $dispatcher->listen(UserRegistered::class, function (UserRegistered $e) use (&$seen) {
        $seen = $e->user['email'] ?? null;
    });

    $assert->that('hasListeners true', $dispatcher->hasListeners(UserRegistered::class));
    $dispatcher->dispatch(new UserRegistered(['email' => 'ada@x.io']));
    $assert->equals('object event reaches listener', 'ada@x.io', $seen);

    // Multiple listeners + return values collected
    $d2 = new Dispatcher();
    $d2->listen('math', fn (int $n) => $n * 2);
    $d2->listen('math', fn (int $n) => $n + 1);
    $results = $d2->dispatch('math', [10]);
    $assert->equals('string event + payload, all listeners', '20,11', implode(',', $results));

    // Returning false halts propagation
    $d3 = new Dispatcher();
    $ran = [];
    $d3->listen('chain', function () use (&$ran) { $ran[] = 'a'; return false; });
    $d3->listen('chain', function () use (&$ran) { $ran[] = 'b'; });
    $d3->dispatch('chain');
    $assert->equals('false halts chain', 'a', implode(',', $ran));

    // Class listener with handle()
    $d4 = new Dispatcher();
    $d4->listen(UserRegistered::class, \App\Listeners\SendWelcomeEmail::class);
    $assert->that('class listener registered', $d4->hasListeners(UserRegistered::class));

    // forget
    $d4->forget(UserRegistered::class);
    $assert->that('forget removes listeners', !$d4->hasListeners(UserRegistered::class));
};

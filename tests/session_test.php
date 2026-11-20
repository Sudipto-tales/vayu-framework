<?php

use Vayu\Session\Session;

return function (Assert $assert): void {
    $session = new Session(native: false);
    $session->start();

    // Basic store
    $session->put('name', 'Ada');
    $assert->equals('put/get', 'Ada', $session->get('name'));
    $assert->that('has()', $session->has('name'));
    $assert->equals('pull removes', 'Ada', $session->pull('name'));
    $assert->that('pulled key gone', !$session->has('name'));

    // CSRF token is stable within a session and non-empty
    $token = $session->token();
    $assert->that('token non-empty', $token !== '');
    $assert->equals('token stable', $token, $session->token());
    $session->regenerateToken();
    $assert->that('token changes after regenerate', $token !== $session->token());

    // Flash lives exactly one request cycle
    $session->flash('status', 'saved');
    $session->flashInput(['email' => 'ada@x.io']);
    $assert->equals('flash available this request', 'saved', $session->get('status'));
    $assert->equals('old input available', 'ada@x.io', $session->old('email'));

    // Simulate next request: aging happens on start()
    $next = new Session(native: false);
    // carry attributes over (what $_SESSION would do)
    foreach ($session->all() as $k => $v) {
        $next->put($k, $v);
    }
    $next->start(); // ages: new -> old (still available this cycle)
    $assert->equals('flash survives into next request', 'saved', $next->get('status'));

    // And the request after that: gone
    $after = new Session(native: false);
    foreach ($next->all() as $k => $v) {
        $after->put($k, $v);
    }
    $after->start();
    $assert->that('flash cleared after two cycles', $after->get('status') === null);
};

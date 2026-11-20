<?php

use Vayu\Config\Config;

return function (Assert $assert): void {
    $config = new Config(dirname(__DIR__) . '/config');

    $assert->that('app.name resolves', $config->get('app.name') !== null);
    $assert->that('missing key returns default', $config->get('app.nope', 'fallback') === 'fallback');

    $config->set('app.custom.nested', 'value');
    $assert->equals('set/get nested', 'value', $config->get('app.custom.nested'));

    $assert->that('has() works for present key', $config->has('app.name'));
    $assert->that('has() works for absent key', !$config->has('app.totally_absent'));
};

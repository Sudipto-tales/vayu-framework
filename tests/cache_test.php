<?php

use Vayu\Cache\ArrayStore;
use Vayu\Cache\FileStore;
use Vayu\Cache\Repository;

return function (Assert $assert): void {
    foreach (['array' => new ArrayStore(), 'file' => new FileStore(sys_get_temp_dir() . '/vayu_cache_' . bin2hex(random_bytes(4)))] as $label => $store) {
        $cache = new Repository($store, 3600);

        $assert->that("[$label] miss returns null", $cache->get('missing') === null);
        $assert->equals("[$label] miss returns default", 'def', $cache->get('missing', 'def'));

        $cache->put('name', 'Ada', 60);
        $assert->equals("[$label] put/get", 'Ada', $cache->get('name'));
        $assert->that("[$label] has()", $cache->has('name'));

        // add() only writes when absent
        $assert->that("[$label] add() on existing is false", !$cache->add('name', 'x'));
        $assert->that("[$label] add() on new is true", $cache->add('fresh', 1));

        // remember caches the closure result
        $calls = 0;
        $compute = function () use (&$calls) { $calls++; return 'computed'; };
        $assert->equals("[$label] remember computes", 'computed', $cache->remember('memo', 60, $compute));
        $assert->equals("[$label] remember cached (no recompute)", 'computed', $cache->remember('memo', 60, $compute));
        $assert->equals("[$label] closure ran once", 1, $calls);

        // increment / decrement
        $cache->put('counter', 10, 60);
        $assert->equals("[$label] increment", 13, $cache->increment('counter', 3));
        $assert->equals("[$label] decrement", 11, $cache->decrement('counter', 2));

        // pull removes
        $cache->put('temp', 'bye', 60);
        $assert->equals("[$label] pull returns", 'bye', $cache->pull('temp'));
        $assert->that("[$label] pull removed", !$cache->has('temp'));

        // forget / flush
        $cache->forget('name');
        $assert->that("[$label] forget", !$cache->has('name'));
        $cache->flush();
        $assert->that("[$label] flush clears all", !$cache->has('fresh'));

        // expiry: a 0-second TTL (already expired) should miss
        $store->put('expired', 'x', -1);
        $assert->that("[$label] expired entry misses", $cache->get('expired') === null);
    }
};

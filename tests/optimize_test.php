<?php

use Vayu\Config\Config;
use Vayu\Console\Commands\ConfigCacheCommand;

return function (Assert $assert): void {
    /* ---- Static safety filter: only pure config files are cacheable ---- */
    $pure = "<?php\nreturn ['name' => env('APP_NAME', 'Vayu'), 'debug' => filter_var(env('X'), FILTER_VALIDATE_BOOLEAN)];";
    $assert->that('pure return array is cacheable', ConfigCacheCommand::isCacheableSource($pure));

    $withClassConst = "<?php\nreturn ['handler' => \\App\\Realtime\\ChatHandler::class, 'list' => []];";
    $assert->that('class-string constant is cacheable', ConfigCacheCommand::isCacheableSource($withClassConst));

    $pdo = "<?php\n\$pdo = new PDO('sqlite:x'); return [];";
    $assert->that('new PDO is NOT cacheable', !ConfigCacheCommand::isCacheableSource($pdo));

    $req = "<?php\nrequire __DIR__ . '/db.php'; return [];";
    $assert->that('require is NOT cacheable', !ConfigCacheCommand::isCacheableSource($req));

    $fn = "<?php\nfunction env(\$k){ return 1; }";
    $assert->that('function declaration is NOT cacheable', !ConfigCacheCommand::isCacheableSource($fn));

    $super = "<?php\nif (\$_SERVER['HTTP_HOST']) {} return [];";
    $assert->that('superglobal access is NOT cacheable', !ConfigCacheCommand::isCacheableSource($super));

    $noReturn = "<?php\n\$x = 1;";
    $assert->that('file without return is NOT cacheable', !ConfigCacheCommand::isCacheableSource($noReturn));

    // Regression: dangerous words in comments/strings must NOT cause a skip.
    $commentBacktick = "<?php\n// start with `php vayu websocket`\nreturn ['x' => 1];";
    $assert->that('backtick in a comment is still cacheable', ConfigCacheCommand::isCacheableSource($commentBacktick));
    $wordsInStrings = "<?php\nreturn ['note' => 'we exec nothing, define nothing', 'h' => \\App\\Chat::class];";
    $assert->that('dangerous words inside strings are cacheable', ConfigCacheCommand::isCacheableSource($wordsInStrings));

    // A config returning a closure cannot be serialized -> not cacheable.
    $closure = "<?php\nreturn ['cb' => function () { return 1; }];";
    $assert->that('closure-valued config is NOT cacheable', !ConfigCacheCommand::isCacheableSource($closure));
    $backtickExec = "<?php\nreturn ['out' => `ls`];";
    $assert->that('real backtick exec is NOT cacheable', !ConfigCacheCommand::isCacheableSource($backtickExec));

    /* ---- Config::preload serves from memory, with file fallback ---- */
    $config = new Config(dirname(__DIR__) . '/config');
    $config->preload(['app' => ['name' => 'Cached', 'env' => 'prod'], 'custom' => ['k' => 'v']]);

    $assert->equals('preloaded value served from memory', 'Cached', $config->get('app.name'));
    $assert->equals('preloaded nested key', 'v', $config->get('custom.k'));
    $assert->equals('missing key in preloaded file returns default', 'd', $config->get('app.nope', 'd'));

    // A key not in the preload map still falls back to loading its file.
    $assert->equals('non-preloaded file loads from disk', 'array', $config->get('cache.stores.array.driver'));
    $assert->that('has() works across preload + files', $config->has('custom.k'));
};

<?php

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeEmail;

/**
 * Event => [listeners] map. Registered with the dispatcher on boot.
 */
return [
    UserRegistered::class => [
        SendWelcomeEmail::class,
    ],
];

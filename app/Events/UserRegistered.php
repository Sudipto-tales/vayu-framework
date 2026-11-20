<?php

namespace App\Events;

class UserRegistered
{
    /**
     * @param array<string, mixed> $user
     */
    public function __construct(public array $user)
    {
    }
}

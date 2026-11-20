<?php

namespace App\Listeners;

use App\Events\UserRegistered;

class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        // A real app would queue/send an email here. We just log it so the
        // example has no external dependency.
        $email = $event->user['email'] ?? 'unknown';
        logger()->info("Welcome email queued for {$email}");
    }
}

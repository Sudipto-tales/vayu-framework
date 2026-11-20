<?php

/**
 * Web routes.
 *
 * @var \Vayu\Routing\Router $router
 */

use App\Controllers\ContactController;
use App\Controllers\WelcomeController;

$router->get('/', [WelcomeController::class, 'index'])->name('home');

$router->get('/hello/{name?}', [WelcomeController::class, 'hello'])->name('hello');

// CSRF + FormRequest validation + flash demo.
$router->get('/contact', [ContactController::class, 'show'])->name('contact.show');
$router->post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

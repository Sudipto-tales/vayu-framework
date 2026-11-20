<?php

namespace App\Controllers;

use Vayu\Http\Request;
use Vayu\Http\Response;

class WelcomeController
{
    public function index(): Response
    {
        return view('welcome');
    }

    public function hello(Request $request, string $name = 'vayu'): Response
    {
        return (new Response())->json([
            'framework' => 'Vayu',
            'message' => "Hello, {$name}!",
            'ip' => $request->ip(),
        ]);
    }
}

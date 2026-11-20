<?php

namespace App\Controllers\Api;

use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Validation\Validator;

class AuthController
{
    /**
     * Issue an API token for valid credentials.
     */
    public function login(Request $request): Response
    {
        $data = Validator::make($request->input(), [
            'email' => 'required|email',
            'password' => 'required',
        ])->validate();

        $user = auth()->validateCredentials($data['email'], $data['password']);

        if ($user === null) {
            return json(['message' => 'Invalid credentials.'], 401);
        }

        $token = auth()->createToken((int) $user['id'], 'api');
        unset($user['password']);

        return json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Return the currently authenticated user (requires `auth` middleware).
     */
    public function me(Request $request): Response
    {
        $user = $request->attribute('user');
        if (is_array($user)) {
            unset($user['password']);
        }

        return json(['user' => $user]);
    }
}

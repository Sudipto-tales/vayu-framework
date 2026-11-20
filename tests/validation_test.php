<?php

use Vayu\Validation\ValidationException;
use Vayu\Validation\Validator;

return function (Assert $assert): void {
    // Passing set
    $v = Validator::make(
        ['name' => 'Ada', 'email' => 'ada@x.io', 'age' => '36'],
        ['name' => 'required|min:2', 'email' => 'required|email', 'age' => 'required|integer']
    );
    $assert->that('valid data passes', $v->passes());
    $assert->equals('validated returns only ruled fields', 'Ada', $v->validated()['name']);

    // Failing set: required, email, min
    $v = Validator::make(
        ['name' => 'A', 'email' => 'nope'],
        ['name' => 'required|min:2', 'email' => 'required|email', 'message' => 'required']
    );
    $assert->that('invalid data fails', $v->fails());
    $errors = $v->errors();
    $assert->that('min error on name', isset($errors['name']));
    $assert->that('email error', isset($errors['email']));
    $assert->that('required error on missing message', isset($errors['message']));

    // confirmed
    $v = Validator::make(
        ['password' => 'secret12', 'password_confirmation' => 'secret12'],
        ['password' => 'required|min:8|confirmed']
    );
    $assert->that('matching confirmation passes', $v->passes());

    $v = Validator::make(
        ['password' => 'secret12', 'password_confirmation' => 'different'],
        ['password' => 'required|confirmed']
    );
    $assert->that('mismatched confirmation fails', $v->fails());

    // in / nullable
    $v = Validator::make(
        ['role' => 'admin', 'nickname' => ''],
        ['role' => 'required|in:user,admin', 'nickname' => 'nullable|min:3']
    );
    $assert->that('in + nullable passes', $v->passes());

    $v = Validator::make(['role' => 'root'], ['role' => 'required|in:user,admin']);
    $assert->that('invalid in value fails', $v->fails());

    // validate() throws on failure
    $threw = false;
    try {
        Validator::make([], ['x' => 'required'])->validate();
    } catch (ValidationException $e) {
        $threw = true;
        $assert->that('exception carries errors', isset($e->errors()['x']));
    }
    $assert->that('validate() throws ValidationException', $threw);

    // custom message
    $v = Validator::make([], ['x' => 'required'], ['x.required' => 'X is mandatory']);
    $assert->equals('custom message used', 'X is mandatory', $v->errors()['x'][0]);
};

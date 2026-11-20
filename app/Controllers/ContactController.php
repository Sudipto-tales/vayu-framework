<?php

namespace App\Controllers;

use App\Requests\ContactRequest;
use Vayu\Http\Response;

class ContactController
{
    public function show(): Response
    {
        return view('contact');
    }

    public function submit(ContactRequest $request): Response
    {
        // $request is already validated here.
        $data = $request->validated();

        session()->flash('status', "Thanks {$data['name']}, your message was received.");

        return back();
    }
}

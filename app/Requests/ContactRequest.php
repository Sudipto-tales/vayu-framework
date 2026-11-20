<?php

namespace App\Requests;

use Vayu\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|min:2|max:60',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ];
    }
}

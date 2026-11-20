<?php

namespace Vayu\Http;

use Vayu\Exceptions\HttpException;
use Vayu\Validation\Validator;

/**
 * A self-validating request.
 *
 *   class StoreUser extends FormRequest {
 *       public function rules(): array {
 *           return ['email' => 'required|email', 'name' => 'required|min:2'];
 *       }
 *   }
 *
 * Type-hint it in a controller method and it validates automatically; on
 * failure a ValidationException is thrown (422 JSON or redirect-back).
 */
abstract class FormRequest extends Request
{
    /** @var array<string, mixed> */
    protected array $validatedData = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Called by the router after resolution.
     */
    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new HttpException(403, 'This action is unauthorized.');
        }

        $data = $this->input();
        foreach ($this->files as $key => $file) {
            $data[$key] = $file;
        }

        $this->validatedData = Validator::make($data, $this->rules(), $this->messages())->validate();
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }
}

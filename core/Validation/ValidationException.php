<?php

namespace Vayu\Validation;

/**
 * Thrown when validation fails. The exception handler turns it into a 422 JSON
 * response or a redirect-back with flashed errors + old input.
 */
class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(protected array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

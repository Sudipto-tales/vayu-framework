<?php

namespace Vayu\Validation;

use Vayu\Database\DB;
use Vayu\Http\UploadedFile;

/**
 * Rule-based validator.
 *
 *   Validator::make($data, [
 *       'email' => 'required|email|unique:users,email',
 *       'password' => 'required|min:8|confirmed',
 *   ])->validate();
 */
class Validator
{
    /** @var array<string, mixed> */
    protected array $data;

    /** @var array<string, list<string>> */
    protected array $rules = [];

    /** @var array<string, string> */
    protected array $messages;

    /** @var array<string, list<string>> */
    protected array $errors = [];

    protected bool $ran = false;

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>             $messages
     */
    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->messages = $messages;

        foreach ($rules as $field => $ruleset) {
            $this->rules[$field] = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
        }
    }

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>             $messages
     */
    public static function make(array $data, array $rules, array $messages = []): static
    {
        return new static($data, $rules, $messages);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function passes(): bool
    {
        $this->run();

        return $this->errors === [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated();
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->run();

        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    protected function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $rules) {
            $names = array_map(fn ($r) => explode(':', $r, 2)[0], $rules);

            // `sometimes`: only validate when present.
            if (in_array('sometimes', $names, true) && !array_key_exists($field, $this->data)) {
                continue;
            }

            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || $value === '' || $value === [];

            if ($isEmpty) {
                if (in_array('required', $names, true)) {
                    $this->addError($field, 'required', []);
                }
                // Empty + not required → skip remaining rules.
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable', 'sometimes'], true)) {
                    continue;
                }

                [$name, $paramStr] = array_pad(explode(':', $rule, 2), 2, '');
                $params = $name === 'regex' ? [$paramStr] : ($paramStr === '' ? [] : explode(',', $paramStr));

                // `confirmed` needs the field name, so handle it here.
                if ($name === 'confirmed') {
                    if (!$this->confirmedMatches($field, $value)) {
                        $this->addError($field, 'confirmed', []);
                    }
                    continue;
                }

                $method = 'validate' . str_replace('_', '', ucwords($name, '_'));
                if (method_exists($this, $method) && $this->{$method}($value, $params) === false) {
                    $this->addError($field, $name, $params);
                }
            }
        }
    }

    /* ----------------------------------------------------------------
     | Rules
     | ---------------------------------------------------------------- */

    protected function validateEmail(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateUrl(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateNumeric(mixed $v): bool
    {
        return is_numeric($v);
    }

    protected function validateInteger(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateString(mixed $v): bool
    {
        return is_string($v);
    }

    protected function validateBoolean(mixed $v): bool
    {
        return in_array($v, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    protected function validateAlpha(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[\pL\pM]+$/u', $v) === 1;
    }

    protected function validateAlphaNum(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[\pL\pM\pN]+$/u', $v) === 1;
    }

    protected function validateAlphaDash(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[\pL\pM\pN_-]+$/u', $v) === 1;
    }

    /**
     * @param list<string> $p
     */
    protected function validateMin(mixed $v, array $p): bool
    {
        return $this->size($v) >= (float) ($p[0] ?? 0);
    }

    /**
     * @param list<string> $p
     */
    protected function validateMax(mixed $v, array $p): bool
    {
        return $this->size($v) <= (float) ($p[0] ?? 0);
    }

    /**
     * @param list<string> $p
     */
    protected function validateBetween(mixed $v, array $p): bool
    {
        $size = $this->size($v);

        return $size >= (float) ($p[0] ?? 0) && $size <= (float) ($p[1] ?? 0);
    }

    /**
     * @param list<string> $p
     */
    protected function validateDigits(mixed $v, array $p): bool
    {
        return ctype_digit((string) $v) && strlen((string) $v) === (int) ($p[0] ?? 0);
    }

    /**
     * @param list<string> $p
     */
    protected function validateIn(mixed $v, array $p): bool
    {
        return in_array((string) $v, $p, true);
    }

    /**
     * @param list<string> $p
     */
    protected function validateNotIn(mixed $v, array $p): bool
    {
        return !in_array((string) $v, $p, true);
    }

    /**
     * @param list<string> $p
     */
    protected function validateSame(mixed $v, array $p): bool
    {
        return isset($p[0]) && ($this->data[$p[0]] ?? null) === $v;
    }

    /**
     * @param list<string> $p
     */
    protected function validateDifferent(mixed $v, array $p): bool
    {
        return isset($p[0]) && ($this->data[$p[0]] ?? null) !== $v;
    }

    /**
     * @param list<string> $p
     */
    protected function validateRegex(mixed $v, array $p): bool
    {
        return isset($p[0]) && @preg_match($p[0], (string) $v) === 1;
    }

    protected function validateDate(mixed $v): bool
    {
        return strtotime((string) $v) !== false;
    }

    /**
     * @param list<string> $p
     */
    protected function validateUnique(mixed $v, array $p): bool
    {
        $table = $p[0] ?? '';
        $column = $p[1] ?? '';
        $ignoreId = $p[2] ?? null;

        if ($table === '' || $column === '') {
            return true;
        }

        $query = DB::table($table)->where($column, $v);
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('id', '!=', $ignoreId);
        }

        return !$query->exists();
    }

    /**
     * @param list<string> $p
     */
    protected function validateExists(mixed $v, array $p): bool
    {
        $table = $p[0] ?? '';
        $column = $p[1] ?? 'id';

        if ($table === '') {
            return true;
        }

        return DB::table($table)->where($column, $v)->exists();
    }

    protected function validateFile(mixed $v): bool
    {
        return $v instanceof UploadedFile && $v->isValid();
    }

    protected function validateImage(mixed $v): bool
    {
        if (!$v instanceof UploadedFile || !$v->isValid()) {
            return false;
        }

        return in_array(strtolower($v->getClientExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    /**
     * @param list<string> $p
     */
    protected function validateMimes(mixed $v, array $p): bool
    {
        return $v instanceof UploadedFile && in_array(strtolower($v->getClientExtension()), $p, true);
    }

    /* ---- size helper ---- */

    protected function size(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }

        if ($v instanceof UploadedFile) {
            return $v->getSize() / 1024; // kilobytes
        }

        if (is_array($v)) {
            return (float) count($v);
        }

        return (float) mb_strlen((string) $v);
    }

    /* ---- errors ---- */

    /**
     * @param list<string> $params
     */
    protected function addError(string $field, string $rule, array $params): void
    {
        $this->errors[$field][] = $this->buildMessage($field, $rule, $params);
    }

    /**
     * @param list<string> $params
     */
    protected function buildMessage(string $field, string $rule, array $params): string
    {
        $attribute = str_replace('_', ' ', $field);

        if (isset($this->messages["{$field}.{$rule}"])) {
            return $this->messages["{$field}.{$rule}"];
        }
        if (isset($this->messages[$rule])) {
            return $this->messages[$rule];
        }

        $templates = [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'url' => 'The :attribute must be a valid URL.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'string' => 'The :attribute must be a string.',
            'boolean' => 'The :attribute field must be true or false.',
            'alpha' => 'The :attribute may only contain letters.',
            'alpha_num' => 'The :attribute may only contain letters and numbers.',
            'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
            'min' => 'The :attribute must be at least :p0.',
            'max' => 'The :attribute may not be greater than :p0.',
            'between' => 'The :attribute must be between :p0 and :p1.',
            'digits' => 'The :attribute must be :p0 digits.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'same' => 'The :attribute and :p0 must match.',
            'different' => 'The :attribute and :p0 must be different.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'regex' => 'The :attribute format is invalid.',
            'date' => 'The :attribute is not a valid date.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'file' => 'The :attribute must be a valid file.',
            'image' => 'The :attribute must be an image.',
            'mimes' => 'The :attribute must be a file of type: :p0.',
        ];

        $message = $templates[$rule] ?? 'The :attribute field is invalid.';
        $message = str_replace(':attribute', $attribute, $message);
        foreach ($params as $i => $param) {
            $message = str_replace(":p{$i}", $param, $message);
        }

        return $message;
    }

    /**
     * `confirmed` is special-cased: compares field with field_confirmation.
     */
    protected function confirmedMatches(string $field, mixed $value): bool
    {
        return ($this->data["{$field}_confirmation"] ?? null) === $value;
    }
}

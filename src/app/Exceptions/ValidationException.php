<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    protected $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render()
    {
        return response()->json([
            'error' => 'Validation Failed',
            'message' => $this->getMessage(),
            'code' => 'VALIDATION_FAILED',
            'errors' => $this->errors,
        ], 422);
    }
}

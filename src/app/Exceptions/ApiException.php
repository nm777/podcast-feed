<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected $errorCode;

    protected $errorType;

    public function __construct(string $message, string $errorCode = 'API_ERROR', int $httpStatusCode = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $httpStatusCode, $previous);
        $this->errorCode = $errorCode;
        $this->errorType = 'API Error';
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;

        return $this;
    }
}

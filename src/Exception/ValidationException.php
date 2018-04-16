<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Exception;

class ValidationException extends RuntimeException
{
    private $validationMessages = [];

    public static function fromMessages(array $messages) : self
    {
        $exception = new self('Unprocessable Entity', 422);
        $exception->validationMessages = $messages;
    }

    public function getValidationMessages() : array
    {
        return $this->validationMessages;
    }
}

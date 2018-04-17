<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Exception;

use Zend\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Zend\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

class ValidationException extends RuntimeException implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function fromMessages(array $messages) : self
    {
        $exception = new self('Unprocessable Entity', 422);
        $exception->status = 422;
        $exception->detail = 'Unprocessable Entity';
        $exception->type = '';
        $exception->title = '';
        $exception->additional = ['messages' => $messages];
        return $exception;
    }
}

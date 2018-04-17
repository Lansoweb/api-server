<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Exception;

use Zend\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Zend\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

class AuthorizationException extends RuntimeException implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create(string $message = 'Not Authorized') : self
    {
        $exception = new self($message, 401);
        $exception->status = 401;
        $exception->detail = $message;
        $exception->type = '';
        $exception->title = '';
        return $exception;
    }
}

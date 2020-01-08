<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Exception;

use Mezzio\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

class MethodNotAllowedException extends RuntimeException implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create(string $message = 'Method Not Allowed') : self
    {
        $exception = new self($message, 405);
        $exception->status = 405;
        $exception->detail = $message;
        $exception->type = '';
        $exception->title = '';
        return $exception;
    }
}

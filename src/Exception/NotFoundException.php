<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Exception;

use Mezzio\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

class NotFoundException extends RuntimeException implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create(string $message = 'Entity Not Found') : self
    {
        $exception = new self($message, 404);
        $exception->status = 404;
        $exception->detail = $message;
        $exception->type = '';
        $exception->title = '';
        return $exception;
    }
}

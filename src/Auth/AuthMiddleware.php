<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Auth;

use LosMiddleware\ApiServer\Exception\AuthorizationException;
use LosMiddleware\ApiServer\Exception\RuntimeException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

/**
 * @deprecated Use los/api-auth
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** @var array */
    private $users = [];
    /** @var array */
    private $allowedPaths = [];
    /** @var ProblemDetailsResponseFactory */
    private $problemDetailsResponseFactory;

    /**
     * Auth constructor.
     * @param array $users
     * @param ProblemDetailsResponseFactory $problemDetailsResponseFactory
     */
    public function __construct(array $users, array $allowedPaths, ProblemDetailsResponseFactory $problemDetailsResponseFactory)
    {
        $this->users = $users;
        $this->allowedPaths = $allowedPaths;
        $this->problemDetailsResponseFactory = $problemDetailsResponseFactory;
    }

    /**
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            $this->validate($request);
        } catch (RuntimeException $ex) {
            return $this->problemDetailsResponseFactory->createResponseFromThrowable($request, $ex);
        }

        return $handler->handle($request);
    }

    /**
     * @param Request $request
     */
    protected function validate(Request $request) : void
    {
        if (in_array($request->getUri()->getPath(), $this->allowedPaths)) {
            return;
        }

        if (! $request->hasHeader('authorization')) {
            throw AuthorizationException::create('Missing Authorization header');
        }

        $token = $request->getHeader('authorization');

        if (empty($token)) {
            throw AuthorizationException::create('Missing Authorization header');
        }
        $token = $token[0];

        if (! preg_match('/^basic/i', $token)) {
            throw AuthorizationException::create('Invalid Authorization header');
        }

        $auth = base64_decode(substr($token, 6));
        if (! $auth) {
            throw AuthorizationException::create('Unable to parse Authorization header');
        }

        $tokens = explode(':', $auth);
        if (count($tokens) != 2) {
            throw AuthorizationException::create('Invalid Authorization header during parse');
        }

        $identity = $tokens[0];
        $credential = $tokens[1];
        if (! array_key_exists($identity, $this->users)) {
            throw AuthorizationException::create('Authorization failed');
        }

        if ($this->users[$identity] != $credential && ! hash_equals($this->users[$identity], $credential)) {
            throw AuthorizationException::create('Authorization failed');
        }
    }
}

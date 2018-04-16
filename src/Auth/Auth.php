<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Auth;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class Auth implements MiddlewareInterface
{
    /** @var array */
    private $users = [];
    /** @var ProblemDetailsResponseFactory */
    private $problemDetailsResponseFactory;

    /**
     * Auth constructor.
     * @param array $users
     * @param ProblemDetailsResponseFactory $problemDetailsResponseFactory
     */
    public function __construct(array $users, ProblemDetailsResponseFactory $problemDetailsResponseFactory)
    {
        $this->users = $users;
        $this->problemDetailsResponseFactory = $problemDetailsResponseFactory;
    }

    /**
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $this->validate($request);

        if ($response !== null) {
            return $response;
        }

        return $handler->handle($request);
    }

    /**
     * @param Request $request
     * @return null|Response
     */
    protected function validate(Request $request) : ?Response
    {
        if (! $request->hasHeader('authorization')) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Missing Authorization header'
            );
        }

        $token = $request->getHeader('authorization');

        if (empty($token)) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Missing Authorization header'
            );
        }
        $token = $token[0];

        if (! preg_match('/^basic/i', $token)) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Invalid Authorization header'
            );
        }

        $auth = base64_decode(substr($token, 6));
        if (! $auth) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Unable to parse Authorization header'
            );
        }

        $tokens = explode(':', $auth);
        if (count($tokens) != 2) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Invalid Authorization header during parse'
            );
        }

        $identity = $tokens[0];
        $credential = $tokens[1];
        if (! array_key_exists($identity, $this->users)) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Authorization failed.'
            );
        }

        if ($this->users[$identity] != $credential && ! hash_equals($this->users[$identity], $credential)) {
            return $this->problemDetailsResponseFactory->createResponse(
                $request,
                401,
                'Authorization failed.'
            );
        }
    }
}

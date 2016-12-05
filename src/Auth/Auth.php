<?php
namespace LosMiddleware\ApiServer\Auth;

use LosMiddleware\ApiServer\Exception\AuthorizationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Db\Adapter\Adapter;
use Zend\Stratigility\MiddlewareInterface;

class Auth implements MiddlewareInterface
{
    private $adapter;
    protected $users = [];

    public function __construct(Adapter $adapter, array $users)
    {
        $this->adapter = $adapter;
        $this->users = $users;
    }

    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        $this->validate($request);

        return $out($request, $response);
    }

    protected function validate(Request $request)
    {
        if (!$request->hasHeader('authorization')) {
            throw new AuthorizationException('Missing Authorization header', 401);
        }

        $token = $request->getHeader('authorization');

        if (empty($token)) {
            throw new AuthorizationException('Missing Authorization header', 401);
        }
        $token = $token[0];

        if ( !preg_match( '/^basic/i', $token ) ) {
            throw new AuthorizationException('Invalid Authorization header', 401);
        }

        $auth = base64_decode(substr($token, 6));
        if (!$auth) {
            throw new AuthorizationException('Unable to parse Authorization header', 401);
        }

        $creds = array_filter(explode(':', $auth));
        if (count($creds) != 2) {
            throw new AuthorizationException('Invalid Authorization header during parse', 401);
        }

        if (!array_key_exists($creds[0], $this->users))
            throw new AuthorizationException('Authorization failed.', 401);
        }

        if ($this->users[$creds[0]] != $creds[1] && !hash_equals($this->users[$creds[0]], $creds[1])) {
            throw new AuthorizationException('Authorization failed.', 401);
        }
    }
}


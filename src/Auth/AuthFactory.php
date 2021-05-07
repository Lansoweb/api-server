<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Auth;

use Interop\Container\ContainerInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

/**
 * @deprecated Use los/api-auth
 */
class AuthFactory
{
    /**
     * @param ContainerInterface $container
     * @return AuthMiddleware
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config')['los']['api_server']['auth'] ?? [];
        $users = $config['clients'] ?? [];
        $allowedPaths = $config['allowedPaths'] ?? [];

        return new AuthMiddleware($users, $allowedPaths, $container->get(ProblemDetailsResponseFactory::class));
    }
}

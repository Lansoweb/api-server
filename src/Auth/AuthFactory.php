<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Auth;

use Interop\Container\ContainerInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class AuthFactory
{
    /**
     * @param ContainerInterface $container
     * @return Auth
     */
    public function __invoke(ContainerInterface $container)
    {
        $users = $container->get('config')['los']['api_server']['auth']['clients'] ?? [];
        return new Auth($users, $container->get(ProblemDetailsResponseFactory::class));
    }
}

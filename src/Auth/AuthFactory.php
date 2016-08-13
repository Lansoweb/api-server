<?php
namespace LosMiddleware\ApiServer\Auth;

use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\Adapter;

class AuthFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $adapter = new Adapter($container->get('config')['db']);

        $users = $container->get('config')['api_server']['auth']['clients'] ?? [];

        return new Auth($adapter, $users);
    }
}

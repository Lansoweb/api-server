<?php

namespace LosMiddleware\ApiServer\Factory;

use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Hydrator\ArraySerializable;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

class RestActionFactory implements AbstractFactoryInterface
{

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return (fnmatch('*Action', $requestedName));
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $entityName = strtolower(str_replace('Action', '', end(explode('\\', $requestedName))));
        $entityClass = str_replace('Action', 'Entity', $requestedName);
        $entity = new $entityClass;

        $config = $container->get('config');
        $adapter = new Adapter($config['db']);
        $table = new TableGateway(
            $config['tables'][$entityName] ?? $entityName,
            $adapter,
            null,
            new HydratingResultSet(new ArraySerializable(), $entity));
        $urlHelper = $container->get(UrlHelper::class);

        return new $requestedName($table, $entity, $urlHelper);
    }
}

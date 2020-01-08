<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Paginator;

use LosMiddleware\ApiServer\Mapper\MapperInterface;
use Laminas\Paginator\Adapter\AdapterInterface;

class MapperAdapter implements AdapterInterface
{
    private $mapper;
    private $where;
    private $order;
    private $group;

    public function __construct(MapperInterface $mapper, array $where = [], $order = null, $group = null)
    {
        $this->mapper = $mapper;
        $this->where = $where;
        $this->order = $order;
        $this->group = $group;
    }

    /**
     * {@inheritDoc}
     * @see \Laminas\Paginator\Adapter\AdapterInterface::getItems()
     */
    public function getItems($offset, $itemCountPerPage)
    {
        return $this->mapper->findBy($this->where, [
            'order' => $this->order,
            'group' => $this->group,
            'offset' => $offset,
            'limit' => $itemCountPerPage,
        ]);
    }

    /**
     * {@inheritDoc}
     * @see Countable::count()
     */
    public function count()
    {
        return $this->mapper->count($this->where);
    }
}

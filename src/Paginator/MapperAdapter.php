<?php
namespace LosMiddleware\ApiServer\Paginator;

use LosMiddleware\ApiServer\Mapper\MapperInterface;
use Zend\Paginator\Adapter\AdapterInterface;
use Zend\Stdlib\ArrayObject;

class MapperAdapter implements AdapterInterface
{
    private $mapper;
    private $where;
    private $order;
    private $group;

    public function __construct(MapperInterface $mapper, ArrayObject $where = null, $order = null, $group = null)
    {
        $this->mapper = $mapper;
        $this->where = $where;
        $this->order = $order;
        $this->group = $group;
    }

    /**
     * {@inheritDoc}
     * @see \Zend\Paginator\Adapter\AdapterInterface::getItems()
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $this->mapper->select($this->where, [
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
        $this->mapper->count($this->where);
    }
}

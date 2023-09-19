<?php

declare(strict_types=1);

namespace Los\ApiServer\Paginator;

use Laminas\Paginator\Adapter\AdapterInterface;
use Los\ApiServer\Mapper\MapperInterface;

class MapperAdapter implements AdapterInterface
{
    public function __construct(
        private MapperInterface $mapper,
        private array $where = [],
        private $order = null,
        private $group = null,
    ) {
    }

    /**
     * {@inheritDoc}
     *
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
     *
     * @see Countable::count()
     */
    public function count(): int
    {
        return $this->mapper->count($this->where);
    }
}

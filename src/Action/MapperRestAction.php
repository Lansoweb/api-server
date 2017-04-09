<?php

namespace LosMiddleware\ApiServer\Action;

use LosMiddleware\ApiServer\Action\AbstractRestAction;
use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use LosMiddleware\ApiServer\Mapper\MapperInterface;
use LosMiddleware\ApiServer\Paginator\MapperAdapter;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Stdlib\ArrayObject;

abstract class MapperRestAction extends AbstractRestAction implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    const SORT_BY = self::IDENTIFIER_NAME;

    protected $mapper;

    protected $limitItemsPerPage = 25;

    public function __construct(MapperInterface $mapper, $entityPrototype, UrlHelper $urlHelper)
    {
        $this->mapper = $mapper;
        $this->entityPrototype = $entityPrototype;
        $this->urlHelper = $urlHelper;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::getResourceName()
     */
    public function getResourceName(): string
    {
        $tokens = explode('\\', get_class($this));
        return strtolower(str_replace('Action', '', end($tokens)));
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::create()
     */
    public function create(array $data): Entity
    {
        $entity = $this->entityPrototype;

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForSql($data);
        $data = new ArrayObject($data);

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->mapper->insert($data);

        return $entity;
    }


    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::fetch()
     */
    public function fetch($id): Entity
    {
        $where = new ArrayObject([static::IDENTIFIER_NAME, $id]);

        $query = $this->request->getQueryParams();

        $this->getEventManager()->trigger(__FUNCTION__, $this, $where);

        $resultSet = $this->mapper->select($where);
        if (count($resultSet) == 0) {
            throw new \Exception('Entity not found', 404);
        }

        /* @var \LosMiddleware\ApiServer\Entity\Entity $entity */
        $entity = $resultSet->current();

        $fields = $query['fields'] ?? [];
        if (!empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }
        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::fetchAll()
     */
    public function fetchAll(): Collection
    {
        /* @var \Zend\Stdlib\Parameters $params */
        $params = $this->request->getQueryParams();


        $sort = null;
        if (isset($params['sort']) && in_array($params['sort'], array_keys($this->entityPrototype->getArrayCopy()))) {
            $sort = [$params['sort'] => isset($params['order']) ? $params['order'] : 'ASC'];
        } else {
            $sort = [static::SORT_BY => 'ASC'];
        }
        $query = $this->request->getQueryParams();
        $fields = $query['fields'] ?? [];
        if (!empty($fields)) {
            $this->table->getResultSetPrototype()->getObjectPrototype()->setFields(explode(',', $fields));
        }

        $where = new ArrayObject([]);
        $this->getEventManager()->trigger(__FUNCTION__, $this, $where);

        $dbAdapter = new MapperAdapter($this->mapper, $where, $sort);
        $collection = new Collection($dbAdapter);

        $itemCountPerPage = $this->itemCountPerPage;
        if (array_key_exists('items_per_page', $params) && is_numeric($params['items_per_page'])) {
            $itemCountPerPage = min([$this->limitItemsPerPage, $params['items_per_page']]);
        }
        $collection->setItemCountPerPage($itemCountPerPage);
        $collection->setCurrentPageNumber($params['page'] ?? 1);

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::delete()
     */
    public function delete($id)
    {
        $where = new ArrayObject([static::IDENTIFIER_NAME => $id]);
        $result = $this->table->select($where);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }

        $this->getEventManager()->trigger(__FUNCTION__, $this, $where);

        $this->table->delete($where);
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::patch()
     */
    public function patch($id, array $data): Entity
    {
        $where = [static::IDENTIFIER_NAME => $id];

        $result = $this->table->select($where);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }
        $entity = $result->current();

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForSql($data);
        $data = new ArrayObject($data);

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->table->update($data, $where);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::update()
     */
    public function update($id, array $data): Entity
    {
        $where = [static::IDENTIFIER_NAME => $id];

        $result = $this->table->select($where);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }
        $entity = $result->current();

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForSql($data);
        $data = new ArrayObject($data);

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->table->update($data, $where);

        return $entity;
    }

}

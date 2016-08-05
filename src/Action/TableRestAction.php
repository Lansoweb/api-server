<?php

namespace LosMiddleware\ApiServer\Action;

use LosMiddleware\ApiServer\Action\AbstractRestAction;
use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\Entity;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Paginator\Adapter\DbTableGateway;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;

abstract class TableRestAction extends AbstractRestAction implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    protected $table;

    public function __construct(TableGateway $table, $entityPrototype, UrlHelper $urlHelper)
    {
        $this->table = $table;
        $this->entityPrototype = $entityPrototype;
        $this->urlHelper = $urlHelper;
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

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->table->insert($data);

        return $entity;
    }


    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::get()
     */
    public function fetch($id): Entity
    {
        $where = [static::IDENTIFIER_NAME => $id];

        $query = $this->request->getQueryParams();

        $this->getEventManager()->trigger(__FUNCTION__, $this, $where);

        $resultSet = $this->table->select($where);
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
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::getList()
     */
    public function fetchAll(): Collection
    {
        $params = $this->request->getQueryParams();
        /* @var \Zend\Stdlib\Parameters $params */
        $where = new Where();

        $sort = null;
        if (isset($params['sort']) && in_array($params['sort'], array_keys($this->entityPrototype->getArrayCopy()))) {
            $sort = [$params['sort'] => isset($params['order']) ? $params['order'] : 'ASC'];
        } else {
            $sort = ['name' => 'ASC'];
        }
        $query = $this->request->getQueryParams();
        $fields = $query['fields'] ?? [];
        if (!empty($fields)) {
            $this->table->getResultSetPrototype()->getObjectPrototype()->setFields(explode(',', $fields));
        }

        $this->getEventManager()->trigger(__FUNCTION__, $this, $where);

        $dbAdapter = new DbTableGateway($this->table, $where, $sort);
        $collection = new Collection($dbAdapter);
        $collection->setItemCountPerPage($this->itemCountPerPage);
        $collection->setCurrentPageNumber(1);

        return $collection;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::delete()
     */
    public function delete($id)
    {
        $result = $this->table->select([static::IDENTIFIER_NAME => $id]);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }

        $this->getEventManager()->trigger(__FUNCTION__, $this, [static::IDENTIFIER_NAME => $id]);

        $this->table->delete(['id' => $id]);
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::patch()
     */
    public function patch($id, array $data): Entity
    {
        $result = $this->table->select([static::IDENTIFIER_NAME => $id]);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }
        $entity = $result->current();

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForSql($data);

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->table->update($data, ['id' => $id]);

        return $entity;
    }

    /**
     * {@inheritDoc}
     * @see \LosMiddleware\ApiServer\Action\AbstractRestAction::update()
     */
    public function update($id, array $data): Entity
    {
        $result = $this->table->select([static::IDENTIFIER_NAME => $id]);
        if ($result->count() == 0) {
            throw new \Exception('Entity not found', 404);
        }
        $entity = $result->current();

        $data = $entity->filterData($data);
        $data = $entity->prepareDataForSql($data);

        $this->getEventManager()->trigger(__FUNCTION__, $this, $data);

        $this->table->update($data, ['id' => $id]);

        return $entity;
    }

}

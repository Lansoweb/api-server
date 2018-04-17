<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Mapper;

use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\EntityInterface;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator\Adapter\DbTableGateway;

class ZendDbMapper implements MapperInterface
{
    /** @var TableGateway */
    protected $table;
    /** @var string */
    private $collectionClass;
    /** @var int */
    protected $limitItemsPerPage = 25;
    /** @var int */
    protected $itemCountPerPage = 25;

    const IDENTIFIER_NAME = 'id';
    const SORT_BY = 'name';

    public function __construct(TableGateway $table, string $collectionClass)
    {
        $this->table = $table;
        $this->collectionClass = $collectionClass;
    }

    /**
     * @param mixed $id
     * @return EntityInterface|null
     */
    public function findById($id): ?EntityInterface
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param array $where
     * @param array $options
     * @return EntityInterface|null
     */
    public function findOneBy(array $where = [], array $options = []): ?EntityInterface
    {
        $predicate = new Where();

        foreach ($where as $key => $value) {
            $predicate->equalTo($key, $value);
        }

        $resultSet = $this->table->select($predicate);
        if (count($resultSet) == 0) {
            return null;
        }

        /* @var EntityInterface $entity */
        $entity = $resultSet->current();
        $fields = (string) ($options['fields'] ?? '');
        if (! empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }

        return $entity;
    }

    public function count(array $where = []): int
    {
        $predicate = new Where();

        foreach ($where as $key => $value) {
            $predicate->equalTo($key, $value);
        }

        $resultSet = $this->table->select($predicate);
        return $resultSet->count();
    }

    public function insert(EntityInterface $entity) : bool
    {
        $data = $entity->prepareDataForStorage();
        return $this->table->insert($data) > 0;
    }

    public function update(array $data, EntityInterface $entity) : bool
    {
        $data = $entity->prepareDataForStorage($data);
        return $this->table->update($data, [self::IDENTIFIER_NAME => $entity->getArrayCopy()['id']]) > 0;
    }

    public function delete(EntityInterface $entity) : bool
    {
        return $this->table->delete([self::IDENTIFIER_NAME => $entity->getArrayCopy()['id']]) > 0;
    }

    public function findBy(array $where = [], array $options = []) : Collection
    {
        $predicate = new Where();

        if (! empty($where)) {
            /** @var HydratingResultSet $resultSetPrototype */
            $resultSetPrototype = $this->table->getResultSetPrototype();
            /** @var EntityInterface $entityPrototype */
            $entityPrototype = $resultSetPrototype->getObjectPrototype();
            $properties = array_keys($entityPrototype->getArrayCopy());
            foreach ($where as $key => $value) {
                if (! in_array($key, $properties) || $key === 'fields') {
                    continue;
                }
                $predicate->equalTo($key, $value);
            }
        }

        $orderBy = $options['sort'] ?? static::SORT_BY;
        $order = [$orderBy => $options['order'] ?? 'ASC'];

        $dbAdapter = new DbTableGateway(
            $this->table,
            $predicate,
            $order,
            $options['group'] ?? [],
            $options['having'] ?? []
        );
        $collection = new $this->collectionClass($dbAdapter);

        return $collection;
    }

    public function setFields(array $fields): void
    {
        /** @var HydratingResultSet $resultSetPrototype */
        $resultSetPrototype = $this->table->getResultSetPrototype();
        /** @var EntityInterface $entityPrototype */
        $entityPrototype = $resultSetPrototype->getObjectPrototype();
        $entityPrototype->setFields($fields);
    }
}

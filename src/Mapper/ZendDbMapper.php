<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Mapper;

use Los\Uql\ZendDbBuilder;
use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\EntityInterface;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;

class ZendDbMapper implements MapperInterface
{
    protected TableGateway $table;
    private string $collectionClass;
    protected int $limitItemsPerPage = 25;
    protected int $itemCountPerPage = 25;

    const IDENTIFIER_NAME = 'id';
    const SORT_BY = 'name';

    /**
     * ZendDbMapper constructor.
     * @param TableGateway $table
     * @param string $collectionClass
     */
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

        /** @var \Laminas\Db\ResultSet\ResultSet $resultSet */
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

    /**
     * @param array $where
     * @return int
     */
    public function count(array $where = []): int
    {
        $predicate = new Where();

        foreach ($where as $key => $value) {
            $predicate->equalTo($key, $value);
        }

        $resultSet = $this->table->select($predicate);
        return $resultSet->count();
    }

    /**
     * @param EntityInterface $entity
     * @return bool
     */
    public function insert(EntityInterface $entity) : bool
    {
        $data = $entity->prepareDataForStorage();
        return $this->table->insert($data) > 0;
    }

    /**
     * @param array $data
     * @param EntityInterface $entity
     * @return bool
     */
    public function update(array $data, EntityInterface $entity) : bool
    {
        $data = $entity->prepareDataForStorage($data);
        return $this->table->update(
            $data,
            [self::IDENTIFIER_NAME => $entity->getArrayCopy()[self::IDENTIFIER_NAME]]
        ) > 0;
    }

    /**
     * @param EntityInterface $entity
     * @return bool
     */
    public function delete(EntityInterface $entity) : bool
    {
        return $this->table->delete([self::IDENTIFIER_NAME => $entity->getArrayCopy()[self::IDENTIFIER_NAME]]) > 0;
    }

    /**
     * @param array $where
     * @param array $options
     * @return Collection
     */
    public function findBy(array $where = [], array $options = []) : Collection
    {
        $sql    = $this->table->getSql();
        $select = $sql->select();
        $select = (new ZendDbBuilder($select))->fromParams($where, $options);

        $dbAdapter = new DbSelect(
            $select,
            $sql,
            $this->table->getResultSetPrototype()
        );

        /** @var Collection $collection */
        $collection = new $this->collectionClass($dbAdapter);

        return $collection;
    }

    /**
     * @param array $fields
     */
    public function setFields(array $fields): void
    {
        /** @var HydratingResultSet $resultSetPrototype */
        $resultSetPrototype = $this->table->getResultSetPrototype();
        /** @var EntityInterface $entityPrototype */
        $entityPrototype = $resultSetPrototype->getObjectPrototype();
        $entityPrototype->setFields($fields);
    }
}

<?php

declare(strict_types=1);

namespace Los\ApiServer\Mapper;

use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Paginator\Adapter\DbSelect;
use Los\ApiServer\Entity\Collection;
use Los\ApiServer\Entity\EntityInterface;
use Los\Uql\ZendDbBuilder;

use function assert;
use function count;
use function explode;

class ZendDbMapper implements MapperInterface
{
    protected int $limitItemsPerPage = 25;
    protected int $itemCountPerPage  = 25;

    public const IDENTIFIER_NAME = 'id';
    public const SORT_BY         = 'name';

    public function __construct(protected TableGateway $table, private string $collectionClass)
    {
    }

    public function findById(mixed $id): EntityInterface|null
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param array $where
     * @param array $options
     */
    public function findOneBy(array $where = [], array $options = []): EntityInterface|null
    {
        $predicate = new Where();

        foreach ($where as $key => $value) {
            $predicate->equalTo($key, $value);
        }

        $resultSet = $this->table->select($predicate);
        assert($resultSet instanceof ResultSet);
        if (count($resultSet) === 0) {
            return null;
        }

        $entity = $resultSet->current();
        assert($entity instanceof EntityInterface);
        $fields = (string) ($options['fields'] ?? '');
        if (! empty($fields)) {
            $entity->setFields(explode(',', $fields));
        }

        return $entity;
    }

    /** @param array $where */
    public function count(array $where = []): int
    {
        $predicate = new Where();

        foreach ($where as $key => $value) {
            $predicate->equalTo($key, $value);
        }

        $resultSet = $this->table->select($predicate);

        return $resultSet->count();
    }

    public function insert(EntityInterface $entity): bool
    {
        $data = $entity->prepareDataForStorage();

        return $this->table->insert($data) > 0;
    }

    /** @param array $data */
    public function update(array $data, EntityInterface $entity): bool
    {
        $data = $entity->prepareDataForStorage($data);

        return $this->table->update(
            $data,
            [self::IDENTIFIER_NAME => $entity->getArrayCopy()[self::IDENTIFIER_NAME]],
        ) > 0;
    }

    public function delete(EntityInterface $entity): bool
    {
        return $this->table->delete([self::IDENTIFIER_NAME => $entity->getArrayCopy()[self::IDENTIFIER_NAME]]) > 0;
    }

    /**
     * @param array $where
     * @param array $options
     */
    public function findBy(array $where = [], array $options = []): Collection
    {
        $sql    = $this->table->getSql();
        $select = $sql->select();
        $select = (new ZendDbBuilder($select))->fromParams($where, $options);

        $dbAdapter = new DbSelect(
            $select,
            $sql,
            $this->table->getResultSetPrototype(),
        );

        $collection = new $this->collectionClass($dbAdapter);
        assert($collection instanceof Collection);

        return $collection;
    }

    /** @param array $fields */
    public function setFields(array $fields): void
    {
        $resultSetPrototype = $this->table->getResultSetPrototype();
        assert($resultSetPrototype instanceof HydratingResultSet);
        $entityPrototype = $resultSetPrototype->getObjectPrototype();
        assert($entityPrototype instanceof EntityInterface);
        $entityPrototype->setFields($fields);
    }
}

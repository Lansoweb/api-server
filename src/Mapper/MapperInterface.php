<?php

declare(strict_types=1);

namespace Los\ApiServer\Mapper;

use Los\ApiServer\Entity\Collection;
use Los\ApiServer\Entity\EntityInterface;

interface MapperInterface
{
    public function findBy(array $where = [], array $options = []): Collection;

    public function findOneBy(array $where = [], array $options = []): EntityInterface|null;

    public function findById($id): EntityInterface|null;

    public function count(array $where = []): int;

    public function insert(EntityInterface $entity): bool;

    public function update(array $data, EntityInterface $entity): bool;

    public function delete(EntityInterface $entity): bool;

    public function setFields(array $fields): void;
}

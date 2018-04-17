<?php
namespace LosMiddleware\ApiServer\Mapper;

use LosMiddleware\ApiServer\Entity\Collection;
use LosMiddleware\ApiServer\Entity\EntityInterface;

interface MapperInterface
{
    public function findBy(array $where = [], array $options = []) : Collection;
    public function findOneBy(array $where = [], array $options = []) : ?EntityInterface;
    public function findById($id) : ?EntityInterface;
    public function count(array $where = []) : int;
    public function insert(EntityInterface $entity) : bool;
    public function update(array $data, EntityInterface $entity) : bool;
    public function delete(EntityInterface $entity) : bool;
    public function setFields(array $fields) : void;
}

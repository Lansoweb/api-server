<?php
namespace LosMiddleware\ApiServer\Mapper;

use LosMiddleware\ApiServer\Entity\EntityInterface;

interface MapperInterface
{
    public function findBy(array $where = [], $options = []) : array;
    public function findOneBy(array $where = [], $options = []) : ?EntityInterface;
    public function findById($id) : ?EntityInterface;
    public function count(array $where = []) : int;
    public function insert($data) : EntityInterface;
    public function update($data, $where = null) : EntityInterface;
    public function delete($where) : EntityInterface;
}

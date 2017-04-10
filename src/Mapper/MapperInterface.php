<?php
namespace LosMiddleware\ApiServer\Mapper;

interface MapperInterface
{
    public function findBy($where = null, $options = []);
    public function findById($id);
    public function count($where = null);
    public function insert($data);
    public function update($data, $where = null);
    public function delete($where);
}

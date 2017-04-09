<?php
namespace LosMiddleware\ApiServer\Mapper;

interface MapperInterface
{
    public function select($where = null, $options = []);
    public function count($where = null);
    public function insert($data);
    public function update($data, $where = null);
    public function delete($where);
}

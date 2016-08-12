<?php
namespace LosMiddleware\ApiServer\Entity;

use Zend\InputFilter\InputFilterAwareInterface;
use Zend\Stdlib\ArraySerializableInterface;

interface EntityInterface extends ArraySerializableInterface, InputFilterAwareInterface
{
    /**
     * Prepares the data array to database operations.
     *
     * Can be used to encode arrays, for example.
     *
     * @param array $data
     * @return array
     */
    public function prepareDataForSql(array $data) : array;
}

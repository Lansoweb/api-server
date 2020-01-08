<?php
namespace LosMiddleware\ApiServer\Entity;

use Laminas\InputFilter\InputFilterAwareInterface;
use Laminas\Stdlib\ArraySerializableInterface;

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
    public function prepareDataForStorage(array $data = []) : array;

    /**
     * @param array $fields
     */
    public function setFields(array $fields) : void;

    /**
     * @param array $data
     * @return array
     */
    public function filterData(array $data) : array;
}

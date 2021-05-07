<?php
declare(strict_types = 1);

namespace LosMiddleware\ApiServer\Entity;

use Laminas\Filter\Word\CamelCaseToUnderscore;
use Laminas\Filter\Word\UnderscoreToStudlyCase;
use Laminas\InputFilter\InputFilterAwareTrait;

class Entity implements EntityInterface
{
    use InputFilterAwareTrait;

    const IDENTIFIER_NAME = 'id';

    protected array $fields = [];

    /**
     * Exchange internal values from provided array.
     * Call a setter. If not available, try the property.
     * @see \Laminas\Stdlib\ArraySerializableInterface::exchangeArray()
     * @param array $data
     */
    public function exchangeArray(array $data)
    {
        $filter = new UnderscoreToStudlyCase();

        foreach ($data as $key => $value) {
            $fieldName = $filter($key);
            $method = 'set'.ucfirst($fieldName);
            if (method_exists($this, $method)) {
                $this->$method($value);
            } elseif (property_exists($this, $fieldName)) {
                $this->$fieldName = $value;
            }
        }
    }

    /**
     * Return an array representation of the object
     * @see \Laminas\Stdlib\ArraySerializableInterface::getArrayCopy()
     */
    public function getArrayCopy() : array
    {

        if (empty($this->fields)) {
            $fields = get_object_vars($this);
            unset($fields['inputFilter']);
            unset($fields['fields']);
            $this->fields = array_keys($fields);
        }
        $filter = new CamelCaseToUnderscore();
        $filterStudly = new UnderscoreToStudlyCase();

        $list = [];
        foreach ($this->fields as $field) {
            $property = $filterStudly($field);
            $fieldName = extension_loaded('mbstring')
                ? mb_strtolower($filter($field))
                : strtolower($filter($field));

            $method = 'get'.ucfirst($property);
            if (method_exists($this, $method)) {
                $list[$fieldName] = $this->$method();
            } elseif (property_exists($this, $property)) {
                $list[$fieldName] = $this->$property;
            } elseif (property_exists($this, $field)) {
                $list[$fieldName] = $this->$field;
            }
        }

        return $list;
    }

    /**
     * Returns the $data filtered by existant properties only.
     *
     * @param array $data
     * @return array
     */
    public function filterData(array $data) : array
    {
        $this->exchangeArray($data);
        return $this->getArrayCopy();
    }

    /**
     * Define which fields will be returned by getArrayCopy
     * @param array $fields
     */
    public function setFields(array $fields) : void
    {
        $this->fields = array_merge([static::IDENTIFIER_NAME], $fields);
    }

    /**
     * @param array $data
     * @return array
     */
    public function prepareDataForStorage(array $data = []) : array
    {
        if (empty($data)) {
            $data = $this->getArrayCopy();
        }
        return $data;
    }
}

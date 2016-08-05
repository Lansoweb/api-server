<?php
namespace LosMiddleware\ApiServer\Entity;

use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterAwareTrait;
use Zend\Stdlib\ArraySerializableInterface;
use Zend\Filter\Word\UnderscoreToStudlyCase;
use Zend\Filter\Word\CamelCaseToUnderscore;

class Entity implements ArraySerializableInterface, InputFilterAwareInterface
{
    use InputFilterAwareTrait;

    protected $fields = [];

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

    public function getArrayCopy() : array
    {

        if (empty($this->fields)) {
            $fields = get_object_vars($this);
            unset($fields['inputFilter']);
            unset($fields['fields']);
            $this->fields = array_keys($fields);
        }

        $filter = new CamelCaseToUnderscore();

        $list = [];
        foreach ($this->fields as $field) {
            $fieldName = extension_loaded('mbstring') ? mb_strtolower($filter($field)) : strtolower($filter($field));
            $method = 'get'.$field;
            if (method_exists($this, $method)) {
                $list[$fieldName] = $this->$method();
            } elseif (property_exists($this, $field)) {
                $list[$fieldName] = $this->$field;
            }
        }

        return $list;
    }

    public function filterData($data) : array
    {
        $this->exchangeArray($data);
        return $this->getArrayCopy();
    }

    public function setFields(array $fields)
    {
        $this->fields = array_merge(['id'], $fields);
    }

    public function prepareDataForSql(array $data) : array
    {
        return $data;
    }

}

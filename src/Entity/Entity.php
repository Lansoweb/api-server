<?php
namespace LosMiddleware\ApiServer\Entity;

use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterAwareTrait;
use Zend\Stdlib\ArraySerializableInterface;

class Entity implements ArraySerializableInterface, InputFilterAwareInterface
{
    use InputFilterAwareTrait;

    public function exchangeArray(array $array)
    {
        foreach ($array as $key => $value) {
            $method = 'set'.ucfirst($key);
            if (method_exists($this, $method)) {
                $this->$method($value);
            } elseif (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function getArrayCopy()
    {
        return get_object_vars($this);
    }
}

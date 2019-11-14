<?php


namespace MaksimM\MultiUnitModels\Traits;


use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitField;

trait ModelConfiguration
{

    /**
     * @return array
     */
    public function getFillable()
    {
        return array_merge($this->getUnitConversionDataColumns(), parent::getFillable());
    }

    /**
     * @return mixed
     */
    public function getHidden()
    {
        return array_merge(parent::getHidden(), $this->getUnitConversionDataColumns());
    }

    public function scopeSelectedUnits($query, $units)
    {
        foreach ($units as $unitBasedColumn => $unit) {
            $query->getModel()->setMultiUnitFieldSelectedUnit($unitBasedColumn, $unit);
        }
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     * @throws NotSupportedMultiUnitField
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);
        foreach ($this->getMultiUnitColumns() as $unitBasedColumn => $options) {
            $model->setMultiUnitFieldSelectedUnit($unitBasedColumn, $this->getMultiUnitFieldSelectedUnit($unitBasedColumn)->getId());
        }
        return $model;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasSetMutator($key)
    {
        if ($this->isMultiUnitField($key)) {
            return true;
        }

        return parent::hasSetMutator($key);
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *coo.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasGetMutator($key)
    {
        if ($this->isMultiUnitField($key) && isset($this->{$key})) {
            return true;
        }

        return parent::hasGetMutator($key);
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return mixed
     */
    public function mutateAttribute($key, $value)
    {
        if ($this->isMultiUnitField($key)) {
            $requestedUnit = $this->getMultiUnitFieldUnit($key);

            $value = $this->getMultiUnitFieldValue($key, new $requestedUnit());
            if (parent::hasGetMutator($key)) {
                return parent::mutateAttribute($key, $value);
            }

            return $value;
        }

        return parent::mutateAttribute($key, $value);
    }

    /**
     * Allows to set input units and process them before multi-unit field.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function fillableFromArray(array $attributes)
    {
        return array_merge($attributes, parent::fillableFromArray($attributes));
    }

    /**
     * Set the value of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return mixed
     */
    protected function setMutatedAttributeValue($key, $value)
    {
        if ($this->isMultiUnitField($key)) {
            $value = $this->processMultiUnitFieldChanges($key, $value);

            if (parent::hasSetMutator($key)) {
                return parent::setMutatedAttributeValue($key, $value);
            }

            return $value;
        }

        parent::setMutatedAttributeValue($key, $value);
    }

    /**
     * Detect changes and set proper database value
     *
     * @param $field
     * @param $value
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return mixed
     */
    private function processMultiUnitFieldChanges($field, $value)
    {
        if(!is_null($value)) {
            $existingConversionData = $this->getMultiUnitExistingConversionData($field);
            if (!is_null($existingConversionData)) {
                $inputUnit = $this->getMultiUnitFieldUnit($field);
                //change existing value only in case if new value doesn't match with stored conversion table or not exists
                if (!isset(
                        $existingConversionData->{$inputUnit->getId()}
                    ) || $value != $existingConversionData->{$inputUnit->getId()}) {
                    $defaultUnitValue = (new $inputUnit($value))->as($this->getMultiUnitFieldDefaultUnit($field));
                    $this->attributes[$field] = $defaultUnitValue;
                } elseif ($value == $existingConversionData->{$inputUnit->getId()}) {
                    //forget changes if value actually isn't changed
                    $defaultUnitValue = $existingConversionData->{$this->getMultiUnitFieldDefaultUnit($field)->getId()};
                    $this->attributes[$field] = $defaultUnitValue;
                    $this->syncOriginalAttribute($field);
                }

                return $value;
            }
        }

        $this->attributes[$field] = $value;

        return $value;
    }
}
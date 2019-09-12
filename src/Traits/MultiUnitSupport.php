<?php

namespace MaksimM\MultiUnitModels\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitField;
use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitFieldUnit;
use UnitConverter\Unit\AbstractUnit;

trait MultiUnitSupport
{
    protected $unitAttributePostfix = '_units';
    protected $unitConversionDataPostfix = '_ucd';
    protected $multiUnitColumns = [];
    protected $multiUnitSelectedUnits = [];

    private function getUnitConversionDataColumns()
    {
        return array_map(function ($column) {
            return $column.$this->getUnitConversionDataPostfix();
        }, array_keys($this->getMultiUnitColumns()));
    }

    private function getUnitConversionUnitColumns()
    {
        return array_map(function ($column) {
            return $column.$this->getUnitAttributePostfix();
        }, array_keys($this->getMultiUnitColumns()));
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
        return array_merge(array_intersect_key($attributes, array_flip($this->getUnitConversionUnitColumns())), parent::fillableFromArray($attributes));
    }

    /**
     * @return array
     */
    public function getFillable()
    {
        return array_merge($this->getUnitConversionDataColumns(), $this->getUnitConversionUnitColumns(), parent::getFillable());
    }

    /**
     * @return mixed
     */
    public function getHidden()
    {
        return array_merge(parent::getHidden(), $this->getUnitConversionDataColumns());
    }

    protected static function bootMultiUnitSupport()
    {
        //save conversion table if base value is changed
        static::creating(function ($model) {
            /**
             * @var Model|MultiUnitSupport $model
             */
            foreach ($model->getMultiUnitColumns() as $unitBasedColumn => $options) {
                if (isset($model->attributes[$unitBasedColumn])) {
                    $model->{$unitBasedColumn.$model->getUnitConversionDataPostfix()} = json_encode(
                        $model->calculateMultiUnitConversionData(
                            $model->attributes[$unitBasedColumn],
                            $model->getMultiUnitFieldUnit($unitBasedColumn, true),
                            $options['supported_units']
                        )
                    );
                    $model->{$unitBasedColumn} = $model->processMultiUnitFieldChanges(
                        $unitBasedColumn,
                        $model->{$unitBasedColumn}
                    );
                }
            }
            //prevent saving of unit columns
            foreach ($model->getUnitConversionUnitColumns() as $unitColumn) {
                if (isset($model->attributes[$unitColumn])) {
                    unset($model->attributes[$unitColumn]);
                }
            }
        });
        static::updating(function ($model) {
            /**
             * @var Model|MultiUnitSupport $model
             */
            foreach (Arr::only($model->getMultiUnitColumns(), array_keys($model->getDirty())) as $unitBasedColumn => $options) {
                $model->{$unitBasedColumn.$model->getUnitConversionDataPostfix()} = json_encode(
                    $model->calculateMultiUnitConversionData(
                        $model->getDirty()[$unitBasedColumn],
                        $model->getMultiUnitFieldUnit($unitBasedColumn, true),
                        $options['supported_units']
                    )
                );
            }
        });
    }

    /**
     * @param              $value
     * @param AbstractUnit $unit
     * @param string[]     $requiredUnits
     *
     * @return array
     */
    private function calculateMultiUnitConversionData($value, AbstractUnit $unit, $requiredUnits)
    {
        $conversionData = [];
        foreach ($requiredUnits as $requiredUnitClass) {
            /**
             * @var AbstractUnit $requiredUnit
             */
            $requiredUnit = new $requiredUnitClass();
            $conversionData[$requiredUnit->getId()] = (new $unit($value))->as($requiredUnit);
        }

        return $conversionData;
    }

    public function getMultiUnitExistingConversionData($field)
    {
        return json_decode($this->{$field.$this->getUnitConversionDataPostfix()} ?? null);
    }

    /**
     * @return string
     */
    public function getUnitAttributePostfix()
    {
        return $this->unitAttributePostfix;
    }

    /**
     * @return string
     */
    protected function getUnitConversionDataPostfix()
    {
        return $this->unitConversionDataPostfix;
    }

    /**
     * @return array
     */
    public function getMultiUnitColumns()
    {
        return $this->multiUnitColumns;
    }

    /**
     * @param $field
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return AbstractUnit[]
     */
    public function getMultiUnitFieldSupportedUnits($field)
    {
        if ($this->isMultiUnitField($field)) {
            return $this->getMultiUnitColumns()[$field]['supported_units'];
        }

        throw new NotSupportedMultiUnitField($field);
    }

    /**
     * @param $field
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return AbstractUnit
     */
    public function getMultiUnitFieldDefaultUnit($field)
    {
        if ($this->isMultiUnitField($field)) {
            $unitClass = $this->getMultiUnitColumns()[$field]['default_unit'];

            return new $unitClass();
        }

        throw new NotSupportedMultiUnitField($field);
    }

    /**
     * @param $field
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return AbstractUnit
     */
    public function getMultiUnitFieldSelectedUnit($field)
    {
        if ($this->isMultiUnitField($field)) {
            $unitClass = $this->multiUnitSelectedUnits[$field] ?? $this->getMultiUnitFieldDefaultUnit($field);

            return new $unitClass();
        }

        throw new NotSupportedMultiUnitField($field);
    }

    /**
     * @param $field
     * @param string $unit
     *
     * @throws NotSupportedMultiUnitField
     * @throws NotSupportedMultiUnitFieldUnit
     */
    public function setMultiUnitFieldSelectedUnit($field, $unit)
    {
        if ($this->isMultiUnitField($field)) {
            $found = false;
            foreach ($this->getMultiUnitFieldSupportedUnits($field) as $unitClass) {
                /**
                 * @var AbstractUnit $unit
                 */
                $supportedUnit = new $unitClass();
                if (strtolower($supportedUnit->getId()) == strtolower($unit)) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $this->multiUnitSelectedUnits[$field] = $unitClass;
            } else {
                throw new NotSupportedMultiUnitFieldUnit($field, $unit);
            }
        } else {
            throw new NotSupportedMultiUnitField($field);
        }
    }

    /**
     * @param        $field
     * @param string $unit
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return mixed
     */
    public function getMultiUnitFieldValueByUnitName($field, $unit = null)
    {
        if ($this->isMultiUnitField($field)) {
            if (isset($this->{$field})) {
                if (is_null($unit)) {
                    $unit = $this->getMultiUnitFieldUnit($field);
                } else {
                    foreach ($this->getMultiUnitFieldSupportedUnits($field) as $unitClass) {
                        /**
                         * @var AbstractUnit $unit
                         */
                        $supportedUnit = new $unitClass();
                        if (strtolower($supportedUnit->getId()) == strtolower($unit)) {
                            $unit = $supportedUnit;
                            break;
                        }
                    }
                }
                if (is_string($unit)) {
                    throw new NotSupportedMultiUnitField($field);
                }
                $existingConversionData = $this->getMultiUnitExistingConversionData($field);
                if (!is_null($existingConversionData) && !is_null($existingConversionData->{$unit->getId()})) {
                    return $existingConversionData->{$unit->getId()};
                }

                return ($this->getMultiUnitFieldSelectedUnit($field)->setValue($this->{$field} ?? $this->attributes[$field]))->as(new $unit());
            } else {
                return;
            }
        }

        throw new NotSupportedMultiUnitField($field);
    }

    /**
     * @param                   $field
     * @param AbstractUnit|null $unit
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return mixed
     */
    public function getMultiUnitFieldValue($field, AbstractUnit $unit = null)
    {
        if ($this->isMultiUnitField($field)) {
            if (isset($this->{$field})) {
                if (is_null($unit)) {
                    $unit = $this->getMultiUnitFieldUnit($field);
                }
                $existingConversionData = $this->getMultiUnitExistingConversionData($field);
                if (!is_null($existingConversionData) && !is_null($existingConversionData->{$unit->getId()})) {
                    return $existingConversionData->{$unit->getId()};
                }

                return ($this->getMultiUnitFieldSelectedUnit($field)->setValue($this->{$field} ?? $this->attributes[$field]))->as(new $unit());
            } else {
                return;
            }
        }

        throw new NotSupportedMultiUnitField($field);
    }

    protected function isMultiUnitField($field)
    {
        return isset($this->getMultiUnitColumns()[$field]);
    }

    /**
     * @param $field
     *
     * @throws NotSupportedMultiUnitField
     *
     * @return AbstractUnit
     */
    protected function getMultiUnitFieldUnit($field, $preferDefault = false)
    {
        if (isset($this->{$field.$this->getUnitAttributePostfix()})) {
            foreach ($this->getMultiUnitFieldSupportedUnits($field) as $unitClass) {
                /**
                 * @var AbstractUnit $unit
                 */
                $unit = new $unitClass();
                if (strtolower($unit->getId()) == strtolower($this->{$field.$this->getUnitAttributePostfix()})) {
                    return $unit;
                }
            }
        }

        return $preferDefault ? $this->getMultiUnitFieldDefaultUnit($field) : $this->getMultiUnitFieldSelectedUnit($field);
    }

    protected function forgetMultiUnitFieldUnitInput($field)
    {
        //prevent column_units to by saved to DB
        if (isset($this->attributes[$field.$this->getUnitAttributePostfix()])) {
            $this->syncOriginalAttribute($field.$this->getUnitAttributePostfix());
        }
    }

    protected function setMultiUnitFieldUnit($field, AbstractUnit $unit)
    {
        if(isset($this->{$field.$this->getUnitAttributePostfix()}))
            $this->{$field.$this->getUnitAttributePostfix()} = $unit->getId();
        $this->forgetMultiUnitFieldUnitInput($field);
    }

    /**
     * @param $field
     *
     * @throws NotSupportedMultiUnitField
     */
    protected function resetMultiUnitFieldUnit($field)
    {
        $this->setMultiUnitFieldUnit($field, $this->getMultiUnitFieldSelectedUnit($field));
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
            $this->attributes[$key] = $value;

            if (parent::hasSetMutator($key)) {
                return parent::setMutatedAttributeValue($key, $value);
            }

            return $value;
        }

        parent::setMutatedAttributeValue($key, $value);
    }

    /**
     * Detect changes and set proper base value.
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
        $existingConversionData = $this->getMultiUnitExistingConversionData($field);
        if (!is_null($existingConversionData)) {
            $inputUnit = $this->getMultiUnitFieldUnit($field);
            //change existing value only in case if new value doesn't match with stored conversion table or not exists
            if (!isset($existingConversionData->{$inputUnit->getId()}) || $value != $existingConversionData->{$inputUnit->getId()}) {
                $this->resetMultiUnitFieldUnit($field);

                return (new $inputUnit($value))->as($this->getMultiUnitFieldDefaultUnit($field));
            } elseif ($value == $existingConversionData->{$inputUnit->getId()}) {
                //forget changes if value actually isn't changed
                $this->resetMultiUnitFieldUnit($field);
                $originalValue = $existingConversionData->{$this->getMultiUnitFieldDefaultUnit($field)->getId()};
                $this->attributes[$field] = $originalValue;
                $this->syncOriginalAttribute($field);

                return $originalValue;
            }
            $this->resetMultiUnitFieldUnit($field);
        }

        return $value;
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
}

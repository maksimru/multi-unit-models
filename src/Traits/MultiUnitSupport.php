<?php

namespace MaksimM\MultiUnitModels\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitField;
use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitFieldUnit;
use UnitConverter\Unit\AbstractUnit;

trait MultiUnitSupport
{
    use ModelConfiguration;

    protected $unitConversionDataPostfix = '_ucd';
    protected $multiUnitColumns = [];
    protected $multiUnitSelectedUnits = [];

    private function getUnitConversionDataColumns()
    {
        return array_map(function ($column) {
            return $column.$this->getUnitConversionDataPostfix();
        }, array_keys($this->getMultiUnitColumns()));
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
                            $model->getMultiUnitFieldUnit($unitBasedColumn),
                            $options['supported_units']
                        )
                    );
                    $model->attributes[$unitBasedColumn] = $model->processMultiUnitFieldChanges(
                        $unitBasedColumn,
                        $model->{$unitBasedColumn}
                    );
                }
            }
        });
        static::updating(function ($model) {
            /**
             * @var Model|MultiUnitSupport $model
             */
            foreach (Arr::only($model->getMultiUnitColumns(), array_keys($model->getDirty())) as $unitBasedColumn => $options) {
                $newValue = $model->attributes[$unitBasedColumn];
                $newValueInDefaultUnits = $model->processMultiUnitFieldChanges(
                    $unitBasedColumn,
                    $newValue
                );
                $model->{$unitBasedColumn.$model->getUnitConversionDataPostfix()} = json_encode(
                    $model->calculateMultiUnitConversionData(
                        $newValue,
                        $model->getMultiUnitFieldUnit($unitBasedColumn),
                        $options['supported_units']
                    )
                );
                $model->attributes[$unitBasedColumn] = $newValueInDefaultUnits;
            }
        });
    }

    /**
     * @param              $value
     * @param AbstractUnit $unit
     * @param string[]     $requiredUnits
     *
     * @return array|null
     */
    private function calculateMultiUnitConversionData($value, AbstractUnit $unit, $requiredUnits)
    {
        if (is_null($value)) {
            return;
        }

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
        return $preferDefault ? $this->getMultiUnitFieldDefaultUnit($field) : $this->getMultiUnitFieldSelectedUnit($field);
    }
}

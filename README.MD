[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/maksimru/multi-unit-models/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/maksimru/multi-unit-models/?branch=master)
[![codecov](https://codecov.io/gh/maksimru/multi-unit-models/branch/master/graph/badge.svg?token=cvgyk9oWEo)](https://codecov.io/gh/maksimru/multi-unit-models)
[![CircleCI](https://circleci.com/gh/maksimru/multi-unit-models.svg?style=svg)](https://circleci.com/gh/maksimru/multi-unit-models)
[![StyleCI](https://github.styleci.io/repos/184490009/shield?branch=master)](https://github.styleci.io/repos/184490009)

# Adds easy ability to store and manipulate multi-units data

# Installation

```bash
composer require maksimru/multi-unit-models
```

# Table structure

You need to have additional text column with postfix "_ucd" (can be customized in model) to keep conversion data and have ability to switch between values with guaranteed of data persistence

```php
Schema::create('vehicles', function (Blueprint $table) {
    $table->increments('vehicle_id');
    $table->double('height')->nullable();
    $table->text('height_ucd');
    $table->double('fuel_consumption_city')->nullable();
    $table->text('fuel_consumption_city_ucd');
    $table->timestamps();
});
```

# Model integration

Add MaksimM\MultiUnitModels\Traits\MultiUnitSupport to your model

```php

namespace MaksimM\MultiUnitModels\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MaksimM\MultiUnitModels\Traits\MultiUnitSupport;
use UnitConverter\Unit\FuelEconomy\KilometrePerLitre;
use UnitConverter\Unit\FuelEconomy\LitrePer100Kilometres;
use UnitConverter\Unit\FuelEconomy\MilesPerGallon;
use UnitConverter\Unit\Length\Kilometre;
use UnitConverter\Unit\Length\Mile;

class Vehicle extends Model
{

    use MultiUnitSupport;

    public $incrementing = true;
    protected $primaryKey = 'vehicle_id';
    protected $table = 'vehicles';

    public function getMultiUnitColumns()
    {
        return [
            //column name
            'height' => [
                //default unit (value will be stored in DB in specified default units)
                'default_unit' => Kilometre::class,
                //additional units that you want to have in your application
                'supported_units' => [
                    Kilometre::class,
                    Mile::class,
                ]
            ],
            'fuel_consumption_city' => [
                'default_unit' => LitrePer100Kilometres::class,
                'supported_units' => [
                    LitrePer100Kilometres::class,
                    KilometrePerLitre::class,
                    MilesPerGallon::class,
                ]
            ],
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'height',
        'fuel_consumption_city',
    ];

}

```

# Features

Model creation and updates:

```php
$model = Vehicle::create([
    'name' => 'test',
    'fuel_consumption_city' => '5'
]);
// fuel_consumption_city is set to 5 L/100Km

$model = Vehicle::create([
    'name' => 'test',
    'fuel_consumption_city' => '5',
    'fuel_consumption_city_units' => 'mpg'
]);
// fuel_consumption_city is set to 5 mpg and DB will have fuel_consumption_city 47.04 (default units L/100Km) as well as $model->fuel_consumption_city will return 47.04

$model->update([
    'fuel_consumption_city' => '1',
    'fuel_consumption_city_units' => 'mpg'
]);
// fuel_consumption_city is set to 1 mpg and DB will have fuel_consumption_city 47.04 (default units L/100Km) as well as $model->fuel_consumption_city will return 47.04

$model->update([
    'fuel_consumption_city' => '1',
]);
// fuel_consumption_city is set to 1 L/100Km
```

Input unit validation:

You can use supported_units validation rule with model class as parameter

```php
$validation = Validator::make([
    'fuel_consumption_city_units' => 'mpg'
], [
    'fuel_consumption_city_units' => 'supported_units:'.Vehicle::class
]);
//validation pass
```


Helper functions:

```php
$model->getMultiUnitFieldSupportedUnits('height')
//return supported units
```

```php
$model->getMultiUnitFieldDefaultUnit('height')->getSymbol()
//return default unit as string: km
```

```php
$model->getMultiUnitFieldValue('height',(new Mile()))
//return specific field in specified unit
```

```php
$model->getMultiUnitFieldValueByUnitName('height','mi')
//return specific field in specified unit
```
<?php

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

    protected function getMultiUnitColumns()
    {
        return [
            //column name
            'height' => [
                //default unit (value will be stored in DB in specified default units)
                'default_unit' => Kilometre::class,
                'supported_units' => [
                    Kilometre::class,
                    Mile::class,
                ],
            ],
            'fuel_consumption_city' => [
                //default unit (value will be stored in DB in specified default units)
                'default_unit' => LitrePer100Kilometres::class,
                'supported_units' => [
                    LitrePer100Kilometres::class,
                    KilometrePerLitre::class,
                    MilesPerGallon::class,
                ],
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
        //do not add "*_ucd" columns, it will be added automatically
    ];
}

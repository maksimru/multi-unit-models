<?php

namespace MaksimM\MultiUnitModels\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use UnitConverter\Unit\FuelEconomy\KilometrePerLitre;
use UnitConverter\Unit\FuelEconomy\LitrePer100Kilometres;
use UnitConverter\Unit\Length\Foot;
use UnitConverter\Unit\Length\Metre;

class BasicUnitConversionTest extends TestCase
{
    /**
     * @test
     *
     * @throws Exception
     */
    public function checkMeasurementConversion()
    {
        $this->assertEquals(30.48, (new Foot(100))->as(new Metre()));
        $this->assertEquals(20, (new LitrePer100Kilometres(5))->as(new KilometrePerLitre()));
    }
}

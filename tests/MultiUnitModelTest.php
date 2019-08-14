<?php

namespace MaksimM\MultiUnitModels\Tests;

use Exception;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Validator;
use MaksimM\MultiUnitModels\Exceptions\NotSupportedMultiUnitField;
use MaksimM\MultiUnitModels\MultiUnitModelsServiceProvider;
use MaksimM\MultiUnitModels\Tests\Models\Vehicle;
use Orchestra\Testbench\BrowserKit\TestCase;
use UnitConverter\Unit\Length\Kilometre;
use UnitConverter\Unit\Length\Mile;

class MultiUnitModelTest extends TestCase
{
    /**
     * @return Vehicle
     */
    private function createStubModel()
    {
        $model = Vehicle::create([
            'name'                  => 'test',
            'height'                => '0.5',
            'fuel_consumption_city' => '5',
        ]);

        return $model;
    }

    /**
     **.
     *
     * @test
     *
     * @throws Exception
     */
    public function validatorFailTest()
    {
        $validation = Validator::make([
            'fuel_consumption_city_units' => 'e',
        ], [
            'fuel_consumption_city_units' => 'supported_units:'.Vehicle::class,
        ]);
        $this->assertTrue($validation->fails());
    }

    /**
     **.
     *
     * @test
     *
     * @throws Exception
     */
    public function validatorPassTest()
    {
        $validation = Validator::make([
            'fuel_consumption_city_units' => 'mpg',
        ], [
            'fuel_consumption_city_units' => 'supported_units:'.Vehicle::class,
        ]);
        $this->assertFalse($validation->fails());
    }

    /**
     * @test
     *
     * @throws Exception
     */
    public function modelCreationTest()
    {
        $model = $this->createStubModel();
        $this->assertInstanceOf(Vehicle::class, $model);
    }

    /**
     * @test
     *
     * @throws Exception
     */
    public function customUnitsCreationTest()
    {
        /**
         * @var $model Vehicle
         */
        $model = Vehicle::create([
            'name'                  => 'test',
            'height_units'          => 'mi',
            'height'                => '1',
            'fuel_consumption_city' => '5',
        ]);
        $this->assertInstanceOf(Vehicle::class, $model);
        $this->assertEquals(1.61, $model->height);
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1.61, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @param  $model_id
     *
     *  @throws Exception
     */
    public function validateCreatedModelTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals(0.5, $model->height);
        $this->assertEquals('km', $model->getMultiUnitFieldDefaultUnit('height')->getSymbol());
        $this->assertEquals(5, $model->fuel_consumption_city);
        $this->assertEquals('L/100km', $model->getMultiUnitFieldDefaultUnit('fuel_consumption_city')->getSymbol());
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @param  $model_id
     *
     *  @throws Exception
     */
    public function validateHiddenFields()
    {
        $model = $this->createStubModel();
        $this->assertEquals([
            'name',
            'height',
            'fuel_consumption_city',
            'updated_at',
            'created_at',
            'vehicle_id',
        ], array_keys($model->toArray()));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @param  $model_id
     *
     *  @throws Exception
     */
    public function validateModelAttributeUpdateTest()
    {
        $model = $this->createStubModel();
        $model->height = 1;
        $model->name = 'test2';
        $model->save();
        $this->assertEquals('test2', $model->name);
        $this->assertEquals(1, $model->height);
        $this->assertEquals(0.62, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @throws Exception
     */
    public function validateModelUpdateTest()
    {
        $model = $this->createStubModel();
        $model->update(['height' => 1, 'name' => 'test2']);
        $this->assertEquals('test2', $model->name);
        $this->assertEquals(1, $model->height);
        $this->assertEquals(0.62, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @throws Exception
     */
    public function validateModelUpdateWithAnotherUnitTest()
    {
        $model = $this->createStubModel();
        $model->update(['height' => 1, 'name' => 'test2', 'height_units' => 'mi']);
        $this->assertEquals('test2', $model->name);
        $this->assertEquals(1.61, $model->height);
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1.61, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @throws Exception
     */
    public function validateModelUpdateWithAnotherUnitReorderedTest()
    {
        $model = $this->createStubModel();
        $model->update(['height_units' => 'mi', 'height' => 1, 'name' => 'test2']);
        $this->assertEquals('test2', $model->name);
        $this->assertEquals(1.61, $model->height);
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1.61, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @throws Exception
     */
    public function validateModelUpdateWithAnotherUnitSameValueTest()
    {
        $model = $this->createStubModel();
        $model->update(['height_units' => 'mi', 'height' => 0.31]);
        $this->assertEquals(0.5, $model->height);
    }

    /** @test
     *  @depends  modelCreationTest
     *
     *  @throws Exception
     */
    public function validateModelUpdateWithAnotherUnitInlineTest()
    {
        $model = $this->createStubModel();
        $model->height_units = 'mi';
        $model->update(['height' => 1, 'name' => 'test2']);
        $this->assertEquals('test2', $model->name);
        $this->assertEquals(1.61, $model->height);
        $this->assertEquals(1, $model->getMultiUnitFieldValue('height', (new Mile())));
        $this->assertEquals(1.61, $model->getMultiUnitFieldValue('height', (new Kilometre())));
    }

    /** @test
     *  @throws Exception
     */
    public function getMultiUnitFieldDefaultUnitTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals('km', $model->getMultiUnitFieldDefaultUnit('height')->getSymbol());
    }

    /** @test
     *  @throws Exception
     */
    public function getMultiUnitFieldValueTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals(0.31, $model->getMultiUnitFieldValue('height', (new Mile())));
    }

    /** @test
     *  @throws Exception
     */
    public function getMultiUnitFieldValueByUnitNameMlTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals(0.31, $model->getMultiUnitFieldValueByUnitName('height', 'mi'));
    }

    /** @test
     *  @throws Exception
     */
    public function getMultiUnitFieldValueByUnitNameKmTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals(0.5, $model->getMultiUnitFieldValueByUnitName('height', 'km'));
    }

    /** @test
     * @throws Exception
     */
    public function exceptionNotSupportedMultiUnitFieldIngetMultiUnitFieldValueByUnitNameXxTest()
    {
        $model = $this->createStubModel();
        $this->expectException(NotSupportedMultiUnitField::class);
        $this->assertEquals(1, $model->getMultiUnitFieldValueByUnitName('height', 'xx'));
    }

    /** @test
     *  @throws Exception
     */
    public function getMultiUnitFieldSupportedUnitsTest()
    {
        $model = $this->createStubModel();
        $this->assertEquals([
            Kilometre::class,
            Mile::class,
        ], $model->getMultiUnitFieldSupportedUnits('height'));
    }

    /** @test
     * @throws Exception
     */
    public function exceptionNotSupportedMultiUnitFieldTest()
    {
        $model = $this->createStubModel();
        $this->expectException(NotSupportedMultiUnitField::class);
        $model->getMultiUnitFieldValue('abcd');
    }

    protected function getPackageProviders($app)
    {
        return [MultiUnitModelsServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        if (!$app['config']->has('database.connections.testing')) {
            $app['config']->set('database.connections.testing', [
                'driver'    => env('DB_DRIVER', 'sqlite'),
                'database'  => env('DB_DATABASE', __DIR__.'/database/'),
                'prefix'    => '',
                'username'  => env('DB_USERNAME', ''),
                'password'  => env('DB_PASSWORD', ''),
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'host'      => env('DB_HOST'),
                'port'      => env('DB_PORT'),
                'modes'     => [
                    'STRICT_TRANS_TABLES',
                    'NO_ZERO_IN_DATE',
                    'NO_ZERO_DATE',
                    'ERROR_FOR_DIVISION_BY_ZERO',
                    'NO_AUTO_CREATE_USER',
                    'NO_ENGINE_SUBSTITUTION',
                ],
                'engine' => null,
            ]);
        }
    }

    /**
     * Setup the test environment.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}

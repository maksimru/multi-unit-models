<?php

namespace MaksimM\MultiUnitModels\Tests;

use Exception;
use MaksimM\MultiUnitModels\MultiUnitModelsServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * @test
     *
     * @throws Exception
     */
    public function validateConfigFile()
    {
        $this->assertArrayHasKey('multi-units', $this->app['config']);
    }

    protected function getPackageProviders($app)
    {
        return [MultiUnitModelsServiceProvider::class];
    }
}

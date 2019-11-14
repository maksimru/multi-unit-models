<?php

namespace MaksimM\MultiUnitModels;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use MaksimM\MultiUnitModels\Traits\MultiUnitSupport;
use UnitConverter\Unit\AbstractUnit;

class MultiUnitModelsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @throws Exception
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            if (!str_contains($this->app->version(), 'Lumen')) {
                $this->publishes([
                    __DIR__.'/../config/multi-units.php' => config_path('multi-units.php'),
                ], 'config');
            }
        }
        Validator::extend('supported_units', function ($attribute, $value, $parameters, $validator) {
            if (2 != count($parameters)) {
                throw new Exception('supported_units validator requires exact 2 parameters');
            }
            $modelClass = $parameters[0];
            $unitField = $parameters[1];
            /**
             * @var $model MultiUnitSupport
             */
            $model = (new $modelClass());

            return in_array($value, array_map(function ($unitClass) {
                /**
                 * @var AbstractUnit $unit
                 */
                $unit = new $unitClass();

                return $unit->getId();
            }, $model->getMultiUnitFieldSupportedUnits($unitField)));
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/multi-units.php', 'multi-units');
    }
}

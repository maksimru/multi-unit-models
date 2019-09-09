<?php

namespace MaksimM\MultiUnitModels\Exceptions;

use Throwable;

class NotSupportedMultiUnitFieldUnit extends \Exception
{
    public function __construct($field, $unit, $code = 0, Throwable $previous = null)
    {
        parent::__construct('Requested field "'.$field.'" doesn\'t support '.$unit, $code, $previous);
    }
}

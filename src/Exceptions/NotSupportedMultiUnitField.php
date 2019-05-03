<?php

namespace MaksimM\MultiUnitModels\Exceptions;

use Throwable;

class NotSupportedMultiUnitField extends \Exception
{
    public function __construct($field, $code = 0, Throwable $previous = null)
    {
        parent::__construct('Requested field "'.$field.'" is not multi-unit field', $code, $previous);
    }
}

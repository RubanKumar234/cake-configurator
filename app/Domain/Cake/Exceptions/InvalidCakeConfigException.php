<?php

namespace App\Domain\Cake\Exceptions;

use Exception;

class InvalidCakeConfigException extends Exception
{
    /** @var string[] */
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct(implode(' ', $errors));
    }
}

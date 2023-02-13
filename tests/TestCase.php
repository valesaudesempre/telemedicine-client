<?php

namespace ValeSaude\TelemedicineClient\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ValeSaude\TelemedicineClient\TelemedicineClientServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TelemedicineClientServiceProvider::class,
        ];
    }
}
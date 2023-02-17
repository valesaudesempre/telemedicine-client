<?php

namespace ValeSaude\TelemedicineClient\Tests;

use Faker\Provider\pt_BR\Person;
use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ValeSaude\TelemedicineClient\TelemedicineClientServiceProvider;

class TestCase extends BaseTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker->addProvider(new Person($this->faker));
    }

    protected function getPackageProviders($app): array
    {
        return [
            TelemedicineClientServiceProvider::class,
        ];
    }
}
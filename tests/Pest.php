<?php

use ValeSaude\TelemedicineClient\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function getFixture(string $filename): string
{
    return file_get_contents(__DIR__."/fixtures/{$filename}");
}

function getFixtureAsJson(string $filename): array
{
    return json_decode(getFixture($filename), true);
}
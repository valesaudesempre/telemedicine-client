<?php

namespace ValeSaude\TelemedicineClient\Tests\Dummies;

class DummyCollectionSubject
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
<?php

namespace ValeSaude\TelemedicineClient\Tests\Dummies;

use ValeSaude\TelemedicineClient\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<DummyCollectionSubject>
 */
class DummyCollection extends AbstractCollection
{
    public function getSubjectClass(): string
    {
        return DummyCollectionSubject::class;
    }
}
<?php

namespace ValeSaude\TelemedicineClient\Builders;

use Carbon\CarbonInterface;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Phone;
use ValeSaude\TelemedicineClient\Data\PatientData;

/**
 * @extends AbstractBuilder<PatientData>
 */
class PatientBuilder extends AbstractBuilder
{
    public function setName(FullName $name): self
    {
        return $this->set('name', $name);
    }

    public function setDocument(Document $document): self
    {
        return $this->set('document', $document);
    }

    public function setBirthDate(CarbonInterface $birthDate): self
    {
        return $this->set('birthDate', $birthDate->toImmutable());
    }

    public function setGender(Gender $gender): self
    {
        return $this->set('gender', $gender);
    }

    public function setEmail(Email $email): self
    {
        return $this->set('email', $email);
    }

    public function setPhone(Phone $phone): self
    {
        return $this->set('phone', $phone);
    }

    public function build(): PatientData
    {
        return new PatientData(
            $this->get('name'),
            $this->get('document'),
            $this->get('birthDate'),
            $this->get('gender'),
            $this->get('email'),
            $this->get('phone')
        );
    }
}
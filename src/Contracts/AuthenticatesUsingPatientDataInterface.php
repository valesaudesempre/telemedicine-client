<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use ValeSaude\TelemedicineClient\Data\PatientData;

interface AuthenticatesUsingPatientDataInterface
{
    public function setPatientDataForAuthentication(PatientData $data): self;
}
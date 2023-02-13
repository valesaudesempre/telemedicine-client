<?php

namespace ValeSaude\TelemedicineClient\Tests\Dummies;

use BadMethodCallException;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;

class DummyScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getSlotsForDoctor(string $doctorId, ?string $specialty = null): AppointmentSlotCollection
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function schedule()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function start()
    {
        throw new BadMethodCallException('Not implemented.');
    }
}
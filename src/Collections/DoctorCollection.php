<?php

namespace ValeSaude\TelemedicineClient\Collections;

use RuntimeException;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;

/**
 * @extends AbstractCollection<Doctor>
 */
class DoctorCollection extends AbstractCollection
{
    public function getSubjectClass(): string
    {
        return Doctor::class;
    }

    public function includesSlots(): bool
    {
        return $this->filter(static fn (Doctor $doctor) => null === $doctor->getSlots())->isEmpty();
    }

    public function sortByDoctorSlot(): DoctorCollection
    {
        if (!$this->includesSlots()) {
            throw new RuntimeException('Cannot sort doctors without slots.');
        }

        return $this->sort(function (Doctor $doctor1, Doctor $doctor2) {
            /** @var AppointmentSlotCollection $doctor1Slots */
            $doctor1Slots = $doctor1->getSlots();
            /** @var AppointmentSlotCollection $doctor2Slots */
            $doctor2Slots = $doctor2->getSlots();
            $sortFunction = static fn (AppointmentSlot $slotA, AppointmentSlot $slotB) => $slotA->getDateTime() <=> $slotB->getDateTime();
            /** @var AppointmentSlot $firstSlot1 */
            $firstSlot1 = $doctor1Slots->sort($sortFunction)->at(0);
            /** @var AppointmentSlot $firstSlot2 */
            $firstSlot2 = $doctor2Slots->sort($sortFunction)->at(0);

            if ($firstSlot1->getDateTime()->equalTo($firstSlot2->getDateTime())) {
                return $doctor1->getName() <=> $doctor2->getName();
            }

            return $firstSlot1->getDateTime() <=> $firstSlot2->getDateTime();
        });
    }
}
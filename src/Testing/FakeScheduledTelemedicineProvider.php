<?php

namespace ValeSaude\TelemedicineClient\Testing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\AuthenticatesUsingPatientDataInterface;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Contracts\SchedulesUsingPatientData;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Enums\AppointmentStatus;

class FakeScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface, SchedulesUsingPatientData, AuthenticatesUsingPatientDataInterface
{
    private Generator $faker;
    private ?PatientData $authenticatedPatientData = null;
    /** @var array<string, Doctor[]> */
    private array $doctors = [];
    /** @var array<string, array<string, AppointmentSlot[]>> */
    private array $slots = [];
    /** @var array<string, array{PatientData, Appointment, bool}> */
    private array $appointments = [];

    public function __construct()
    {
        $this->faker = Factory::create(config('app.faker_locale', Factory::DEFAULT_LOCALE));
    }

    public function setPatientDataForAuthentication(PatientData $data): AuthenticatesUsingPatientDataInterface
    {
        $this->authenticatedPatientData = $data;

        return $this;
    }

    public function getDoctors(?string $specialty = null, ?string $name = null): DoctorCollection
    {
        $this->ensureAuthenticationPatientDataIsSet();

        $doctors = [];

        if ($specialty) {
            $doctors = $this->doctors[$specialty] ?? [];
        } else {
            $doctors = Arr::flatten($this->doctors);
        }

        $uniqueDoctors = [];

        /** @var Doctor $doctor */
        foreach ($doctors as $doctor) {
            if (array_key_exists($doctor->getId(), $uniqueDoctors)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            if ($name && !$doctor->getName()->equals(FullName::fromFullNameString($name))) {
                continue;
            }

            $uniqueDoctors[$doctor->getId()] = $doctor;
        }

        return new DoctorCollection($uniqueDoctors);
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null,
        ?int $limit = null
    ): AppointmentSlotCollection {
        $this->ensureAuthenticationPatientDataIsSet();

        $slots = [];

        foreach ($this->slots[$doctorId] ?? [] as $doctorSpecialty => $doctorSlots) {
            if ($specialty && $specialty != $doctorSpecialty) {
                continue;
            }

            $slots = array_merge(
                $slots,
                array_filter(
                    $doctorSlots,
                    static fn (AppointmentSlot $slot) => $until === null || !$slot->getDateTime()->isAfter($until)
                )
            );
        }

        if ($limit) {
            $slots = array_slice($slots, 0, $limit);
        }

        return new AppointmentSlotCollection($slots);
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null,
        ?int $slotLimit = null
    ): DoctorCollection {
        $this->ensureAuthenticationPatientDataIsSet();

        $doctors = $this->getDoctors($specialty);

        if ($doctorId) {
            $doctors = $doctors->filter(static fn (Doctor $doctor) => $doctor->getId() == $doctorId);
        }

        $doctorsWithFilteredSlots = [];

        /** @var Doctor $doctor */
        foreach ($doctors as $doctor) {
            $slots = $this->getSlotsForDoctor($doctor->getId(), $specialty, $until, $slotLimit);

            if (!count($slots)) {
                continue;
            }

            $doctorsWithFilteredSlots[] = new Doctor(
                $doctor->getId(),
                $doctor->getName(),
                $doctor->getRegistrationNumber(),
                $doctor->getPhoto(),
                $slots
            );
        }

        return new DoctorCollection($doctorsWithFilteredSlots);
    }

    public function scheduleUsingPatientData(string $specialty, string $slotId, PatientData $patientData): Appointment
    {
        $this->ensureAuthenticationPatientDataIsSet();

        $slot = $this->getSlot($slotId);

        if (!$slot) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The slot id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        $identifier = $this->generatePatientDataIdentifier($patientData);
        $key = "{$specialty}:{$identifier}:{$slotId}";
        $appointment = new Appointment(
            Str::uuid(),
            $slot->getDateTime(),
            AppointmentStatus::SCHEDULED,
            $this->faker->name()
        );

        $this->appointments[$key] = [$patientData, $appointment, false];

        return $appointment;
    }

    public function getAppointment(string $appointmentId): Appointment
    {
        $this->ensureAuthenticationPatientDataIsSet();

        $appointment = $this->getCreatedAppointment($appointmentId);

        if (!$appointment) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The appointment id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        return $appointment;
    }

    public function getAppointmentLink(string $appointmentId): string
    {
        $this->ensureAuthenticationPatientDataIsSet();

        if (!$this->appointmentExists($appointmentId)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The appointment id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        return "http://some.url/appointments/{$appointmentId}";
    }

    public function cancelAppointment(string $appointmentId): void
    {
        $this->ensureAuthenticationPatientDataIsSet();

        if (!$this->appointmentExists($appointmentId)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The appointment id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        $this->markAppointmentAsCanceled($appointmentId);
    }

    /**
     * @codeCoverageIgnore
     */
    public function cacheUntil(CarbonInterface $cacheTtl): self
    {
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withoutCache(): self
    {
        return $this;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, Doctor[]>
     */
    public function getMockedDoctors(): array
    {
        return $this->doctors;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, AppointmentSlot[]>>
     */
    public function getMockedSlots(): array
    {
        return $this->slots;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array{PatientData, Appointment, false}>
     */
    public function getMockedAppointments(): array
    {
        return array_filter(
            $this->appointments,
            static fn (array $appointmentData) => !$appointmentData[2]
        );
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array{PatientData, Appointment, true}>
     */
    public function getCanceledMockedAppointments(): array
    {
        return array_filter(
            $this->appointments,
            static fn (array $appointmentData) => $appointmentData[2]
        );
    }

    public function mockExistingDoctor(string $specialty, ?Doctor $doctor = null): Doctor
    {
        if (!$doctor) {
            $doctor = new Doctor(
                (string) Str::uuid(),
                FullName::fromFullNameString($this->faker->name),
                'CRM-SP 12345'
            );
        }

        if (!array_key_exists($specialty, $this->doctors)) {
            $this->doctors[$specialty] = [];
        }

        $this->doctors[$specialty][$doctor->getId()] = $doctor;

        return $doctor;
    }

    public function mockExistingDoctorSlot(string $doctorId, string $specialty, ?AppointmentSlot $slot = null): AppointmentSlot
    {
        /** @var Doctor|null $doctor */
        $doctor = $this->doctors[$specialty][$doctorId] ?? null;

        if (!isset($doctor)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The doctor id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        if (!$slot) {
            $slot = new AppointmentSlot(
                (string) Str::uuid(),
                CarbonImmutable::now()->addHour()->startOfHour(),
            );
        }

        if (!array_key_exists($doctorId, $this->slots)) {
            $this->slots[$doctorId] = [];
        }

        if (!array_key_exists($specialty, $this->slots[$doctorId])) {
            $this->slots[$doctorId][$specialty] = [];
        }

        $this->slots[$doctorId][$specialty][] = $slot;
        $this->doctors[$specialty][$doctor->getId()] = new Doctor(
            $doctor->getId(),
            $doctor->getName(),
            $doctor->getRegistrationNumber(),
            $doctor->getPhoto(),
            ($doctor->getSlots() ?? new AppointmentSlotCollection())->add($slot)
        );

        return $slot;
    }

    public function mockExistingAppointment(string $specialty, string $slotId, PatientData $patientData): Appointment
    {
        $identifier = $this->generatePatientDataIdentifier($patientData);
        $key = "{$specialty}:{$identifier}:{$slotId}";
        $appointment = new Appointment(
            Str::uuid(),
            CarbonImmutable::make($this->faker->dateTime()),
            AppointmentStatus::SCHEDULED,
            $this->faker->name()
        );

        $this->appointments[$key] = [$patientData, $appointment, false];

        return $appointment;
    }

    public function mockCanceledAppointment(string $appointmentId): void
    {
        if (!$this->appointmentExists($appointmentId)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The appointment id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        $this->markAppointmentAsCanceled($appointmentId);
    }

    /**
     * @param (callable(string $specialty, string $slotId, PatientData $patientData, Appointment $appointment): bool)|null $assertion
     */
    public function assertAppointmentCreated(?callable $assertion = null, bool $includeCanceled = false): void
    {
        if (null === $assertion) {
            $appointments = $includeCanceled
                ? $this->getCanceledMockedAppointments()
                : $this->getMockedAppointments();
            Assert::assertNotEmpty($appointments, 'No appointments were created.');

            return;
        }

        Assert::assertTrue($this->appointmentWasCreated($assertion, $includeCanceled), 'The appointment was not created.');
    }

    /**
     * @param (callable(string $specialty, string $slotId, PatientData $patientData, Appointment $appointment): bool)|null $assertion
     */
    public function assertAppointmentNotCreated(?callable $assertion = null, bool $includeCanceled = false): void
    {
        if (null === $assertion) {
            $appointments = $includeCanceled
                ? $this->getCanceledMockedAppointments()
                : $this->getMockedAppointments();
            Assert::assertEmpty($appointments, 'Some appointments were created.');

            return;
        }

        Assert::assertFalse($this->appointmentWasCreated($assertion, $includeCanceled), 'The appointment was created.');
    }

    /**
     * @param (callable(string $specialty, string $slotId, PatientData $patientData, Appointment $appointment): bool)|null $assertion
     */
    public function assertAppointmentCanceled(?callable $assertion = null): void
    {
        if (null === $assertion) {
            Assert::assertNotEmpty($this->getCanceledMockedAppointments(), 'No appointments were canceled.');

            return;
        }

        Assert::assertTrue($this->appointmentWasCanceled($assertion), 'The appointment was not canceled.');
    }

    private function ensureAuthenticationPatientDataIsSet(): void
    {
        if (!$this->authenticatedPatientData) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The patient data is not set.');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function getSlot(string $slotId): ?AppointmentSlot
    {
        /** @var AppointmentSlot $slot */
        foreach (Arr::flatten($this->slots) as $slot) {
            if ($slot->getId() === $slotId) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @codeCoverageIgnore
     */
    private function appointmentExists(string $appointmentId): bool
    {
        /** @var Appointment $appointment */
        foreach ($this->appointments as [, $appointment, $canceled]) {
            if (!$canceled && $appointment->getId() === $appointmentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    private function getCreatedAppointment(string $appointmentId): ?Appointment
    {
        /** @var Appointment $appointment */
        foreach ($this->appointments as [, $appointment, $canceled]) {
            if (!$canceled && $appointment->getId() === $appointmentId) {
                return $appointment;
            }
        }

        return null;
    }

    private function generatePatientDataIdentifier(PatientData $patientData): string
    {
        return md5(serialize(Arr::sort(get_object_vars($patientData))));
    }

    /**
     * @param callable(string $specialty, string $slotId, PatientData $patientData, Appointment $appointment): bool $assertion
     */
    private function appointmentWasCreated(callable $assertion, bool $includeCanceled = false): bool
    {
        foreach ($this->appointments as $key => [$patientData, $appointment, $canceled]) {
            [$specialty, , $slotId] = explode(':', $key);

            if ($canceled && !$includeCanceled) {
                continue;
            }

            if ($assertion($specialty, $slotId, $patientData, $appointment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(string $specialty, string $slotId, PatientData $patientData, Appointment $appointment): bool $assertion
     */
    private function appointmentWasCanceled(callable $assertion): bool
    {
        foreach ($this->getCanceledMockedAppointments() as $key => [$patientData, $appointment]) {
            [$specialty, , $slotId] = explode(':', $key);

            if ($assertion($specialty, $slotId, $patientData, $appointment)) {
                return true;
            }
        }

        return false;
    }

    private function markAppointmentAsCanceled(string $appointmentId): void
    {
        foreach ($this->appointments as $key => [, $appointment]) {
            if ($appointment->getId() === $appointmentId) {
                $this->appointments[$key][2] = true;

                break;
            }
        }
    }
}

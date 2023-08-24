<?php

namespace ValeSaude\TelemedicineClient\Testing;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Faker\Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use ValeSaude\LaravelValueObjects\Document;
use ValeSaude\LaravelValueObjects\Email;
use ValeSaude\LaravelValueObjects\FullName;
use ValeSaude\LaravelValueObjects\Gender;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\LaravelValueObjects\Phone;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Data\PatientData;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Entities\Patient;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class FakeScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    /** @var Generator */
    private $faker;

    /** @var array<string, Doctor[]> */
    private array $doctors = [];

    /** @var array<string, array<string, AppointmentSlot[]>> */
    private array $slots = [];

    /** @var array<string, Patient> */
    private array $patients = [];

    /** @var array<string, Appointment> */
    private array $appointments = [];

    public function __construct(Generator $faker)
    {
        $this->faker = $faker;

        if (!\in_array(\Faker\Provider\Person::class, $this->faker->getProviders())) {
            $this->faker->addProvider(new \Faker\Provider\Person($this->faker));
        }
    }

    public function getDoctors(?string $specialty = null): DoctorCollection
    {
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

            $uniqueDoctors[$doctor->getId()] = $doctor;
        }

        return new DoctorCollection($uniqueDoctors);
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection {
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

        return new AppointmentSlotCollection($slots);
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection {
        $doctors = $this->getDoctors($specialty);

        if ($doctorId) {
            $doctors = $doctors->filter(static fn (Doctor $doctor) => $doctor->getId() == $doctorId);
        }

        if ($until) {
            $doctorsWithFilteredSlots = [];

            /** @var Doctor $doctor */
            foreach ($doctors as $doctor) {
                $slots = $this->getSlotsForDoctor($doctor->getId(), $specialty, $until);

                if (!count($slots)) {
                    continue;
                }

                $doctorsWithFilteredSlots[] = new Doctor(
                    $doctor->getId(),
                    $doctor->getName(),
                    $doctor->getGender(),
                    $doctor->getRating(),
                    $doctor->getRegistrationNumber(),
                    $doctor->getPhoto(),
                    $slots
                );
            }

            $doctors = new DoctorCollection($doctorsWithFilteredSlots);
        }

        return $doctors;
    }

    public function getPatient(string $id): ?Patient
    {
        return $this->patients[$id] ?? null;
    }

    public function updateOrCreatePatient(PatientData $data): Patient
    {
        $existingPatient = collect($this->patients)
            ->first(static fn (Patient $patient) => $data->getDocument()->equals($patient->getDocument()));
        $id = $existingPatient ? $existingPatient->getId() : (string) Str::uuid();
        $patient = Patient::fromData($data, $id);

        // @phpstan-ignore-next-line
        $this->patients[$id] = $patient;

        return $patient;
    }

    public function schedule(string $specialty, string $patientId, string $slotId): Appointment
    {
        if (!isset($this->patients[$patientId])) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The patient id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        if (!$this->slotExists($slotId)) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('The slot id is not valid.');
            // @codeCoverageIgnoreEnd
        }

        $key = "{$specialty}:{$patientId}:{$slotId}";
        $appointment = new Appointment(Str::uuid(), CarbonImmutable::create(2024, 1, 1, 12));

        $this->appointments[$key] = $appointment;

        return $appointment;
    }

    /**
     * @codeCoverageIgnore
     */
    public function start()
    {
        throw new BadMethodCallException('Not implemented.');
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
     * @return array<string, Patient>
     */
    public function getMockedPatients(): array
    {
        return $this->patients;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, Appointment>
     */
    public function getMockedAppointments(): array
    {
        return $this->appointments;
    }

    public function mockExistingDoctor(string $specialty, ?Doctor $doctor = null): Doctor
    {
        if (!$doctor) {
            $doctor = new Doctor(
                (string) Str::uuid(),
                new FullName($this->faker->firstName(), $this->faker->lastName()),
                new Gender($this->faker->randomElement(['M', 'F'])),
                new Rating(10),
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
                new Money(10000)
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
            $doctor->getGender(),
            $doctor->getRating(),
            $doctor->getRegistrationNumber(),
            $doctor->getPhoto(),
            ($doctor->getSlots() ?? new AppointmentSlotCollection())->add($slot)
        );

        return $slot;
    }

    public function mockExistingPatient(?Patient $patient = null): Patient
    {
        if (!$patient) {
            $patient = new Patient(
                Str::uuid(),
                new FullName($this->faker->firstName(), $this->faker->lastName()),
                Document::generateCPF(),
                // @phpstan-ignore-next-line
                CarbonImmutable::create(2000),
                new Gender($this->faker->randomElement(['M', 'F'])),
                new Email($this->faker->safeEmail()),
                new Phone($this->faker->numerify('26#########'))
            );
        }

        $this->patients[$patient->getId()] = $patient;

        return $patient;
    }

    public function mockExistingAppointment(string $specialty, string $patientId, string $slotId): Appointment
    {
        $key = "{$specialty}:{$patientId}:{$slotId}";
        $appointment = new Appointment(
            Str::uuid(),
            CarbonImmutable::make($this->faker->dateTime()),
            $this->faker->sentence()
        );

        $this->appointments[$key] = $appointment;

        return $appointment;
    }

    /**
     * @param callable(Patient): bool|null $assertion
     */
    public function assertPatientCreated(?callable $assertion = null): void
    {
        if (null === $assertion) {
            Assert::assertNotEmpty($this->patients, 'No patients were created.');

            return;
        }

        $wasCreated = false;

        foreach ($this->patients as $patient) {
            if ($assertion($patient)) {
                $wasCreated = true;
                break;
            }
        }

        Assert::assertTrue($wasCreated, 'The patient was not created.');
    }

    /**
     * @param callable(Patient): bool|null $assertion
     */
    public function assertPatientNotCreated(?callable $assertion = null): void
    {
        if (null === $assertion) {
            Assert::assertEmpty($this->patients, 'Some patients were created.');

            return;
        }

        $wasNotCreated = false;

        foreach ($this->patients as $patient) {
            if ($assertion($patient)) {
                $wasNotCreated = true;
                break;
            }
        }

        Assert::assertFalse($wasNotCreated, 'The patient was created.');
    }

    /**
     * @param callable(Appointment): bool|null $assertion
     */
    public function assertAppointmentCreated(?callable $assertion = null): void
    {
        if (null === $assertion) {
            Assert::assertNotEmpty($this->appointments, 'No appointments were created.');

            return;
        }

        $wasCreated = false;

        foreach ($this->appointments as $appointment) {
            if ($assertion($appointment)) {
                $wasCreated = true;
                break;
            }
        }

        Assert::assertTrue($wasCreated, 'The appointment was not created.');
    }

    /**
     * @param callable(Appointment): bool|null $assertion
     */
    public function assertAppointmentNotCreated(?callable $assertion = null): void
    {
        if (null === $assertion) {
            Assert::assertEmpty($this->appointments, 'Some appointments were created.');

            return;
        }

        $wasNotCreated = false;

        foreach ($this->appointments as $appointment) {
            if ($assertion($appointment)) {
                $wasNotCreated = true;
                break;
            }
        }

        Assert::assertFalse($wasNotCreated, 'The appointment was created.');
    }

    /**
     * @codeCoverageIgnore
     */
    private function slotExists(string $slotId): bool
    {
        /** @var AppointmentSlot $slot */
        foreach (Arr::flatten($this->slots) as $slot) {
            if ($slot->getId() === $slotId) {
                return true;
            }
        }

        return false;
    }
}
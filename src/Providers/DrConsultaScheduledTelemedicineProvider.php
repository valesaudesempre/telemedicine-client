<?php

namespace ValeSaude\TelemedicineClient\Providers;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class DrConsultaScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;
    private int $defaultUnitId;
    private ?string $token = null;

    public function __construct(string $baseUrl, string $clientId, string $secret, int $defaultUnitId)
    {
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->defaultUnitId = $defaultUnitId;
    }

    public function authenticate(): string
    {
        $token = $this
            ->newRequest(false)
            ->post('v1/login/auth', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
            ])
            ->throw() // Tratar erros conhecidos
            ->json('access_token');

        $this->token = $token;

        return $token;
    }

    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $doctors = new DoctorCollection();

        foreach ($response as $doctor) {
            $doctors->add(
                new Doctor(
                    $doctor['id_profissional'],
                    $doctor['nome'],
                    $doctor['sexo'],
                    new Rating($doctor['nota']),
                    $doctor['nrp'],
                    data_get($doctor, 'fotos.small') ?: null
                )
            );
        }

        return $doctors;
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $slots = new AppointmentSlotCollection();

        foreach ($response as $doctor) {
            if ($doctor['id_profissional'] != $doctorId) {
                continue;
            }

            $slots = $this->parseSlots($doctor['horarios'], $until);
        }

        return $slots;
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $doctors = new DoctorCollection();

        foreach ($response as $doctor) {
            if ($doctorId && $doctor['id_profissional'] != $doctorId) {
                continue;
            }

            $slots = $this->parseSlots($doctor['horarios'], $until);

            if (!count($slots)) {
                continue;
            }

            $doctors->add(
                new Doctor(
                    $doctor['id_profissional'],
                    $doctor['nome'],
                    $doctor['sexo'],
                    new Rating($doctor['nota']),
                    $doctor['nrp'],
                    data_get($doctor, 'fotos.small') ?: null,
                    $slots
                )
            );
        }

        return $doctors;
    }

    /**
     * @codeCoverageIgnore
     */
    public function schedule()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     */
    public function start()
    {
        throw new BadMethodCallException('Not implemented.');
    }

    private function ensureIsAuthenticated(): void
    {
        if (!isset($this->token)) {
            $this->authenticate();
        }
    }

    private function newRequest(bool $withToken = true): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)->asJson();

        if ($withToken) {
            // @phpstan-ignore-next-line
            $request->withToken($this->token);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDoctorsWithSlotsResponse(?string $specialty = null): array
    {
        $this->ensureIsAuthenticated();

        $payload = ['idUnidade' => $this->defaultUnitId];
        if ($specialty) {
            $payload['idProduto'] = $specialty;
        }

        return $this
            ->newRequest()
            ->get('v1/profissional/slotsAtivos', $payload)
            ->throw() // Tratar erros conhecidos
            ->json();
    }

    /**
     * @param array<string, mixed> $doctorSlots
     */
    private function parseSlots(array $doctorSlots, ?CarbonInterface $until = null): AppointmentSlotCollection
    {
        $slots = new AppointmentSlotCollection();

        foreach ($doctorSlots as $slot) {
            /** @var CarbonImmutable $date */
            $date = CarbonImmutable::make($slot['horario']);

            if ($until && $date->isAfter($until)) {
                continue;
            }

            $slots->add(new AppointmentSlot($slot['id_slot'], $date, Money::fromFloat($slot['preco'])));
        }

        return $slots;
    }
}
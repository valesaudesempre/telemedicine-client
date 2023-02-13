<?php

namespace ValeSaude\TelemedicineClient\Providers;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class DrConsultaProvider implements ScheduledTelemedicineProviderInterface
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
        $response = $this
            ->newRequest(false)
            ->post('v1/login/auth', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
            ])
            ->throw(); // Tratar erros conhecidos

        return $response->json('access_token');
    }

    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        $response = $this->getDoctorsWithSlots($specialty);
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

    public function getSlotsForDoctor(string $doctorId, ?string $specialty = null): AppointmentSlotCollection
    {
        $response = $this->getDoctorsWithSlots($specialty);
        $slots = new AppointmentSlotCollection();

        foreach ($response as $doctor) {
            if ($doctor['id_profissional'] != $doctorId) {
                continue;
            }

            foreach ($doctor['horarios'] as $slot) {
                $slots->add(
                    new AppointmentSlot(
                        $slot['id_slot'],
                        // @phpstan-ignore-next-line
                        CarbonImmutable::make($slot['horario']),
                        Money::fromFloat($slot['preco'])
                    )
                );
            }
        }

        return $slots;
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
    private function getDoctorsWithSlots(?string $specialty = null): array
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
}
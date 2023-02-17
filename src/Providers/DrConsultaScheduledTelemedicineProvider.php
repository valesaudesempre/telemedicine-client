<?php

namespace ValeSaude\TelemedicineClient\Providers;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use ValeSaude\LaravelValueObjects\Money;
use ValeSaude\TelemedicineClient\Collections\AppointmentSlotCollection;
use ValeSaude\TelemedicineClient\Collections\DoctorCollection;
use ValeSaude\TelemedicineClient\Concerns\HasCacheHandlerTrait;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Entities\Appointment;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\Entities\Patient;
use ValeSaude\TelemedicineClient\Exceptions\AppointmentSlotNotFoundException;
use ValeSaude\TelemedicineClient\Exceptions\DoctorNotFoundException;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class DrConsultaScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    use HasCacheHandlerTrait;

    private string $clientId;
    private string $secret;
    private string $marketplaceBaseUrl;
    private string $marketplaceToken;
    private int $marketplaceDefaultUnitId;
    private string $subscriptionBaseUrl;
    private string $subscriptionBasicAuthUsername;
    private string $subscriptionBasicAuthPassword;
    private string $subscriptionPartnerCode;

    public function __construct(
        string $clientId,
        string $secret,
        string $marketplaceBaseUrl,
        int $marketplaceDefaultUnitId,
        string $subscriptionBaseUrl,
        string $subscriptionUsername,
        string $subscriptionPassword,
        string $subscriptionPartnerCode,
        CacheRepository $cache
    ) {
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->marketplaceBaseUrl = $marketplaceBaseUrl;
        $this->subscriptionBaseUrl = $subscriptionBaseUrl;
        $this->marketplaceDefaultUnitId = $marketplaceDefaultUnitId;
        $this->subscriptionBasicAuthUsername = $subscriptionUsername;
        $this->subscriptionBasicAuthPassword = $subscriptionPassword;
        $this->subscriptionPartnerCode = $subscriptionPartnerCode;
        $this->cache = $cache;
    }

    public function authenticateMarketplace(): string
    {
        $token = $this
            ->newMarketplaceRequest(false)
            ->post('v1/login/auth', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
            ])
            ->throw() // Tratar erros conhecidos
            ->json('access_token');

        $this->marketplaceToken = $token;

        return $token;
    }

    public function getDoctors(?string $specialty = null): DoctorCollection
    {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $doctors = new DoctorCollection();

        foreach ($response as $item) {
            $doctors->add(
                new Doctor(
                    data_get($item, 'profissional.id_profissional'),
                    data_get($item, 'profissional.nome'),
                    data_get($item, 'profissional.sexo'),
                    new Rating(data_get($item, 'profissional.nota')),
                    data_get($item, 'profissional.nrp'),
                    data_get($item, 'profissional.fotos.small') ?: null
                )
            );
        }

        return $doctors;
    }

    /**
     * @throws DoctorNotFoundException
     */
    public function getDoctor(string $doctorId, bool $withSlots = false): Doctor
    {
        $response = $this->getDoctorsWithSlotsResponse();

        foreach ($response as $item) {
            if (data_get($item, 'profissional.id_profissional') != $doctorId) {
                continue;
            }

            $slots = null;
            if ($withSlots) {
                $slots = $this->parseSlots($item['horarios']);
            }

            return new Doctor(
                data_get($item, 'profissional.id_profissional'),
                data_get($item, 'profissional.nome'),
                data_get($item, 'profissional.sexo'),
                new Rating(data_get($item, 'profissional.nota')),
                data_get($item, 'profissional.nrp'),
                data_get($item, 'profissional.fotos.small') ?: null,
                $slots
            );
        }

        throw DoctorNotFoundException::withId($doctorId);
    }

    public function getSlotsForDoctor(
        string $doctorId,
        ?string $specialty = null,
        ?CarbonInterface $until = null
    ): AppointmentSlotCollection {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $slots = new AppointmentSlotCollection();

        foreach ($response as $item) {
            if (data_get($item, 'profissional.id_profissional') != $doctorId) {
                continue;
            }

            $slots = $this->parseSlots($item['horarios'], $until);
        }

        return $slots;
    }

    public function getDoctorsWithSlots(
        ?string $specialty = null,
        ?string $doctorId = null,
        ?CarbonInterface $until = null
    ): DoctorCollection
    {
        $response = $this->getDoctorsWithSlotsResponse($specialty);
        $doctors = new DoctorCollection();

        foreach ($response as $item) {
            if ($doctorId && data_get($item, 'profissional.id_profissional') != $doctorId) {
                continue;
            }

            $slots = $this->parseSlots($item['horarios'], $until);

            if (!count($slots)) {
                continue;
            }

            $doctors->add(
                new Doctor(
                    data_get($item, 'profissional.id_profissional'),
                    data_get($item, 'profissional.nome'),
                    data_get($item, 'profissional.sexo'),
                    new Rating(data_get($item, 'profissional.nota')),
                    data_get($item, 'profissional.nrp'),
                    data_get($item, 'profissional.fotos.small') ?: null,
                    $slots
                )
            );
        }

        return $doctors;
    }

    public function getDoctorSlot(string $doctorId, string $slotId): AppointmentSlot
    {
        $response = $this->getDoctorsWithSlotsResponse();

        foreach ($response as $item) {
            if (data_get($item, 'profissional.id_profissional') != $doctorId) {
                continue;
            }

            foreach ($item['horarios'] as $slot) {
                if ($slot['id_slot'] == $slotId) {
                    return new AppointmentSlot(
                        $slot['id_slot'],
                        // @phpstan-ignore-next-line
                        CarbonImmutable::make($slot['horario']),
                        Money::fromFloat($slot['preco'])
                    );
                }
            }
        }

        throw AppointmentSlotNotFoundException::withDoctorIdAndSlotId($doctorId, $slotId);
    }

    public function updateOrCreatePatient(Patient $patient): string
    {
        return $this
            ->newSubscriptionRequest(true)
            ->post('v1/subscription', [
                'cpf' => $patient->getCpf()->getNumber(),
                'nome' => $patient->getName(),
                'mail' => (string) $patient->getEmail(),
                'matricula' => $patient->getCpf()->getNumber(),
                'sexo' => $patient->getGender(),
                'nasc' => $patient->getBirthDate()->toDateString(),
                'codigo_parceiro' => $this->subscriptionPartnerCode,
            ])
            ->throw()
            ->json('id_paciente');
    }

    public function schedule(string $specialty, string $doctorId, string $slotId, string $patientId): Appointment
    {
        $this->ensureMarketplaceIsAuthenticated();

        $response = $this
            ->newMarketplaceRequest()
            ->post('v1/agendamento', [
                'idPaciente' => $patientId,
                'idUnidade' => $this->marketplaceDefaultUnitId,
                'idProduto' => $specialty,
                'idSlot' => $slotId,
            ])
            ->throw();

        // FIXME: Alterar nome da propriedade quando API estiver funcional
        return new Appointment($response->json('hash'));
    }

    /**
     * @codeCoverageIgnore
     */
    public function start(string $appointmentIdentifier): string
    {
        throw new BadMethodCallException('Not implemented.');
    }

    private function ensureMarketplaceIsAuthenticated(): void
    {
        if (!isset($this->marketplaceToken)) {
            $this->authenticateMarketplace();
        }
    }

    private function newMarketplaceRequest(bool $withToken = true): PendingRequest
    {
        $request = Http::baseUrl($this->marketplaceBaseUrl)->asJson();

        if ($withToken) {
            // @phpstan-ignore-next-line
            $request->withToken($this->marketplaceToken);
        }

        return $request;
    }

    private function newSubscriptionRequest(bool $withBasicAuth = false): PendingRequest
    {
        $request = Http::baseUrl($this->subscriptionBaseUrl)->asJson();

        if ($withBasicAuth) {
            // @phpstan-ignore-next-line
            $request->withBasicAuth($this->subscriptionBasicAuthUsername, $this->subscriptionBasicAuthPassword);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDoctorsWithSlotsResponse(?string $specialty = null): array
    {
        $payload = ['idUnidade' => $this->marketplaceDefaultUnitId];
        if ($specialty) {
            $payload['idProduto'] = $specialty;
        }

        $cacheKey = 'scheduled.telemedicine.providers:dr-consulta:schedule';
        if ($specialty) {
            $cacheKey .= ":{$specialty}";
        }

        return $this->handlePossibilyCachedCall(
            $cacheKey,
            function () use ($payload) {
                $this->ensureMarketplaceIsAuthenticated();

                return $this->newMarketplaceRequest()
                    ->get('v1/profissional/slotsAtivos', $payload)
                    ->throw() // Tratar erros conhecidos
                    ->json();
            }
        );
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
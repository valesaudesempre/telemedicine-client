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
use ValeSaude\TelemedicineClient\Concerns\HasCacheHandlerTrait;
use ValeSaude\TelemedicineClient\Contracts\DrConsultaConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Contracts\ScheduledTelemedicineProviderInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class DrConsultaScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    use HasCacheHandlerTrait;

    private string $marketplaceBaseUrl;
    private int $marketplaceDefaultUnitId;
    private string $healthPlanBaseUrl;
    private string $healthPlanContractId;
    private string $clientId;
    private string $secret;
    private ?string $marketplaceToken = null;
    private ?string $healthPlanToken = null;

    public function __construct(
        SharedConfigRepositoryInterface $sharedConfig,
        DrConsultaConfigRepositoryInterface $providerConfig
    ) {
        $this->marketplaceBaseUrl = $providerConfig->getMarketplaceBaseUrl();
        $this->marketplaceDefaultUnitId = $providerConfig->getMarketplaceDefaultUnitId();
        $this->healthPlanBaseUrl = $providerConfig->getHealthPlanBaseUrl();
        $this->healthPlanContractId = $providerConfig->getHealthPlanContractId();
        $this->clientId = $providerConfig->getClientId();
        $this->secret = $providerConfig->getSecret();
        $this->cache = $sharedConfig->getCache();
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

    public function authenticateHealthPlan(): string
    {
        $token = $this
            ->newHealthPlanRequest(false)
            ->post('v1/login/auth', [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
            ])
            ->throw() // Tratar erros conhecidos
            ->json('access_token');

        $this->healthPlanToken = $token;

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
    ): DoctorCollection {
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

    private function ensureMarketplaceIsAuthenticated(): void
    {
        if (!isset($this->marketplaceToken)) {
            $this->authenticateMarketplace();
        }
    }

    private function newRequest(string $baseUrl, ?string $token): PendingRequest
    {
        $request = Http::baseUrl($baseUrl)->asJson();

        if ($token) {
            $request->withToken($token);
        }

        return $request;
    }

    private function newMarketplaceRequest(bool $withToken = true): PendingRequest
    {
        return $this->newRequest($this->marketplaceBaseUrl, $withToken ? $this->marketplaceToken : null);
    }

    private function newHealthPlanRequest(bool $withToken = true): PendingRequest
    {
        return $this->newRequest($this->healthPlanBaseUrl, $withToken ? $this->healthPlanToken : null);
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

        return $this->handlePossiblyCachedCall(
            $cacheKey,
            function () use ($payload) {
                $this->ensureMarketplaceIsAuthenticated();

                return $this
                    ->newMarketplaceRequest()
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
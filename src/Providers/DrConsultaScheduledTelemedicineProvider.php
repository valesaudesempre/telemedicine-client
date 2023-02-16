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
use ValeSaude\TelemedicineClient\Entities\AppointmentSlot;
use ValeSaude\TelemedicineClient\Entities\Doctor;
use ValeSaude\TelemedicineClient\ValueObjects\Rating;

class DrConsultaScheduledTelemedicineProvider implements ScheduledTelemedicineProviderInterface
{
    use HasCacheHandlerTrait;

    private string $baseUrl;
    private string $clientId;
    private string $secret;
    private int $defaultUnitId;
    private ?string $token = null;

    public function __construct(
        string $baseUrl,
        string $clientId,
        string $secret,
        int $defaultUnitId,
        CacheRepository $cache
    ) {
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->defaultUnitId = $defaultUnitId;
        $this->cache = $cache;
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
    ): AppointmentSlotCollection
    {
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

        $cacheKey = 'scheduled.telemedicine.providers:dr-consulta:schedule';
        if ($specialty) {
            $cacheKey .= ":{$specialty}";
        }

        return $this->handlePossibilyCachedCall(
            $cacheKey,
            fn () => $this
                ->newRequest()
                ->get('v1/profissional/slotsAtivos', $payload)
                ->throw() // Tratar erros conhecidos
                ->json()
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
<?php

namespace ValeSaude\TelemedicineClient\Contracts;

use Illuminate\Contracts\Cache\Repository;

interface SharedConfigRepositoryInterface
{
    /**
     * @return class-string<ScheduledTelemedicineProviderInterface>
     */
    public function getScheduledTelemedicineProviderClass(string $provider): string;

    public function getCache(): Repository;
}
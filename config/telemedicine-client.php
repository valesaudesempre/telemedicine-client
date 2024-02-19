<?php

use ValeSaude\TelemedicineClient\Enums\ScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Providers\FleuryScheduledTelemedicineProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            ScheduledTelemedicineProvider::DR_CONSULTA => DrConsultaScheduledTelemedicineProvider::class,
            ScheduledTelemedicineProvider::FLEURY => FleuryScheduledTelemedicineProvider::class,
        ],
    ],

    'cache-store' => null,
];
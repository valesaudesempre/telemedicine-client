<?php

use ValeSaude\TelemedicineClient\Enums\ScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            ScheduledTelemedicineProvider::DR_CONSULTA => DrConsultaScheduledTelemedicineProvider::class,
        ],
    ],

    'cache-store' => null,
];
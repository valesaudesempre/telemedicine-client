<?php

use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            'dr-consulta' => DrConsultaScheduledTelemedicineProvider::class,
        ],
    ],

    'cache-store' => null,
];
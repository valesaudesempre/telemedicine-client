<?php

use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            'drconsulta' => DrConsultaScheduledTelemedicineProvider::class,
        ],
    ],
];
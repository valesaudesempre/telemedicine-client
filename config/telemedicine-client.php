<?php

use ValeSaude\TelemedicineClient\Providers\DrConsultaProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            'drconsulta' => DrConsultaProvider::class,
        ],
    ],
];
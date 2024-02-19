<?php

use ValeSaude\TelemedicineClient\Enums\ScheduledTelemedicineProvider;
use ValeSaude\TelemedicineClient\Providers\FleuryScheduledTelemedicineProvider;

return [
    'scheduled-telemedicine' => [
        'providers' => [
            ScheduledTelemedicineProvider::FLEURY => FleuryScheduledTelemedicineProvider::class,
        ],
    ],

    'cache-store' => null,
];
<?php

declare(strict_types=1);

return [

    /*
    | The Bono control add-on. Price lives here so a client rate is never
    | hard-coded in signup or invoice logic. Amounts are decimal strings.
    */
    'bono_control' => [
        'name' => 'Bono control',
        'price' => env('BONO_CONTROL_MONTHLY_PRICE', '0.00'),
        'currency' => 'ETB',
        'trial_days' => (int) env('BONO_CONTROL_TRIAL_DAYS', 14),
    ],

];

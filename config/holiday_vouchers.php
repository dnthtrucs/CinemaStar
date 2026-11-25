<?php

return [
    'enabled' => env('HOLIDAY_VOUCHERS_ENABLED', true),
    'advance_days' => 30,
    // Vietnamese lunar dates must be verified before adding another year.
    'tet_dates' => [2027 => '2027-02-06'],
];

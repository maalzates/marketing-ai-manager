<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator emails
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of emails that receive the `admin` role on their first
    | Google login. Everyone else gets `user`.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ADMIN_EMAILS', 'maalzates@gmail.com')),
    ))),

];

<?php

return [

    'name' => 'San Josef National High School',
    'logo' => 'assets/school-logo.png',

    // Base URL of the legacy vanilla PHP app, used for pages not yet converted.
    'legacy_url' => rtrim(env('LEGACY_APP_URL', 'http://localhost/Capstoneafter/Capstoneafter'), '/'),

    'login' => [
        'failure_window_minutes' => 30,
        'max_failures' => 5,
        'lockout_minutes' => 5,
    ],

    'password_reset' => [
        'code_ttl_minutes' => 15,
    ],

    'name_suffixes' => ['JR', 'SR', 'II', 'III', 'IV'],

];

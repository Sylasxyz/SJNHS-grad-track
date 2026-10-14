<?php

return [

    'name' => 'San Josef National High School',
    'logo' => 'assets/school-logo.png',

    // Base URL of the legacy vanilla PHP app, used for pages not yet converted.
    'legacy_url' => rtrim(env('LEGACY_APP_URL', 'http://localhost/Capstoneafter/Capstoneafter'), '/'),

    // Folder that contains uploads/ (the legacy app root). Image_Path values such as
    // "uploads/announcements/a3_1700000000.jpg" are relative to it, shared by both apps.
    'uploads_root' => rtrim(env('LEGACY_UPLOADS_ROOT', dirname(base_path())), '/\\'),

    'login' => [
        'failure_window_minutes' => 30,
        'max_failures' => 5,
        'lockout_minutes' => 5,
        // Failed attempts (per username + IP, inside the window) that raise an admin Security Alert.
        'alert_threshold' => 5,
    ],

    // User-safe staff notices, same wording as includes/app_config.php.
    'messages' => [
        'staff_email_not_sent' => 'The change was saved, but the notification email could not be delivered. Please inform the user manually if needed.',
        'staff_schema_pending' => 'Some dashboard features are unavailable until the system is fully configured. Please contact the administrator.',
    ],

    'password_reset' => [
        'code_ttl_minutes' => 15,
    ],

    'name_suffixes' => ['JR', 'SR', 'II', 'III', 'IV'],

];

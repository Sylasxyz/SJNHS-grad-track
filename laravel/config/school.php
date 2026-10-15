<?php

return [

    'name' => 'San Josef National High School',
    'logo' => 'assets/school-logo.png',

    // Public pages (homepage, about, footer), same values as includes/app_config.php.
    // Image settings accept a path under public/ or a full URL.
    'hero_image' => 'assets/landing/students-hero.png',
    'about_image' => 'assets/school-about-annex.png',
    'location_label' => 'Cabanatuan City',
    'tagline' => 'Committed to Quality Education for a Brighter Tomorrow.',
    'address' => 'Brgy. San Josef, Cabanatuan City',
    'email' => 'info@sjnhs.edu.ph',
    'phone' => '(044) 803-0048',
    'social' => [
        'facebook' => '#',
        'instagram' => '#',
    ],

    // Folder that contains uploads/ (the project root, one level above laravel/; a volume on Railway).
    // Image_Path / Profile_Picture values such as "uploads/announcements/a3_1700000000.jpg" are relative to it.
    'uploads_root' => rtrim(env('UPLOADS_ROOT', dirname(base_path())), '/\\'),

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

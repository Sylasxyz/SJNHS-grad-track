<?php
declare(strict_types=1);

require_once __DIR__ . '/url_helpers.php';

// Branding (used on the landing page + top bar)
$SCHOOL_NAME = 'San Josef National High School';
$SCHOOL_LOGO_URL = 'assets/school-logo.png'; // e.g. 'https://your-site.com/logo.png' or 'assets/logo.png'
$SCHOOL_HERO_IMAGE_URL = 'assets/landing/students-hero.png';
$SCHOOL_ABOUT_IMAGE_URL = 'assets/school-about-annex.png';

// Optional landing page images (carousel). Use full URLs or local paths like 'assets/landing/1.jpg'
$LANDING_CAROUSEL_IMAGES = [
    // 'assets/landing/1.jpg',
    // 'assets/landing/2.jpg',
    // 'assets/landing/3.jpg',
];

$SCHOOL_LOCATION_LABEL = 'Cabanatuan City';
$SCHOOL_TAGLINE = 'Committed to Quality Education for a Brighter Tomorrow.';
$SCHOOL_ADDRESS = 'Brgy. San Josef, Cabanatuan City';
$SCHOOL_EMAIL = 'info@sjnhs.edu.ph';
$SCHOOL_PHONE = '(044) 803-0048';

// Footer / legal links (use # for placeholders until real pages exist)
$SCHOOL_PRIVACY_URL = 'privacy';
$SCHOOL_TERMS_URL = 'terms';

// Optional social links shown in the footer.
$SCHOOL_SOCIAL_FACEBOOK = '#';
$SCHOOL_SOCIAL_INSTAGRAM = '#';
$SCHOOL_SOCIAL_YOUTUBE = '#';

// Change this to a strong secret (do not share).
// Used to protect admin self-registration.
$ADMIN_SETUP_KEY = 'Adminxyzonlyaccess';

// Optional extra hardening: allow admin self-registration only from these IPs.
// Leave empty to allow from anywhere.
$ADMIN_REGISTER_ALLOWED_IPS = [
    // '127.0.0.1',
    // '::1',
];

// Mail/SMTP settings live in includes/mail_config.php (used by password reset + approvals).

/** User-safe copy — no paths, host names, or deployment hints. */
function msg_registration_unavailable(): string
{
    return 'Registration is temporarily unavailable. Please try again later or contact the school office for assistance.';
}

function msg_staff_schema_pending(): string
{
    return 'Some dashboard features are unavailable until the system is fully configured. Please contact the administrator.';
}

function msg_staff_email_not_sent(): string
{
    return 'The change was saved, but the notification email could not be delivered. Please inform the user manually if needed.';
}

function msg_password_reset_code_fallback(): string
{
    return 'Email delivery is unavailable. Use the verification code below to continue resetting your password.';
}

function msg_password_reset_show_code_success(): string
{
    return 'We could not send the verification email. Use the code below to continue. It expires in 15 minutes.';
}


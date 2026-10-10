<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_roles(['Instructor', 'Admin']);
app_redirect('dashboard');
exit;


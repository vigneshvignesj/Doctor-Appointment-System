<?php

// Local dev: point these at a test SMTP inbox (e.g. Mailtrap) so credential/
// confirmation emails can be inspected without sending real mail through XAMPP.
// SMTP password is not stored here — it's loaded from a gitignored,
// project-local .env (MAIL_USERNAME / MAIL_PASSWORD) so it never gets committed.
$mailEnv = [];
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    $mailEnv = parse_ini_file($envFile) ?: [];
}

return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => $mailEnv['MAIL_USERNAME'] ?? 'your-email@gmail.com',
    'password' => $mailEnv['MAIL_PASSWORD'] ?? '',
    'encryption' => 'tls',
    'from_email' => $mailEnv['MAIL_USERNAME'] ?? 'your-email@gmail.com',
    'from_name' => 'TealCare Clinic',
];

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/../admin/includes/email_variables.php';
require_once __DIR__ . '/conekta_client.php';
require_once __DIR__ . '/stripe_client.php';
require_once __DIR__ . '/recaptcha_client.php';
require_once __DIR__ . '/cart.php';

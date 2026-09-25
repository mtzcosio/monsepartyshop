<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('settings.php'));
}

$to = trim($_POST['test_email'] ?? '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    flash_set('Ingresa un correo válido para la prueba.', 'error');
    redirect(admin_url('settings.php'));
}

$store_name = get_setting('store_name', 'Monse Party Shop');
$result = send_email(
    $to,
    '📧 Correo de prueba - ' . $store_name,
    "¡Hola!\n\nEste es un correo de prueba enviado desde el panel de {$store_name} para confirmar que la configuración de correo funciona correctamente.\n\n¡Todo listo! 💕"
);

flash_set($result['message'], $result['success'] ? 'success' : 'error');
redirect(admin_url('settings.php'));

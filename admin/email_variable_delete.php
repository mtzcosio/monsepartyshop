<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('email_variables.php'));
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = get_db()->prepare('DELETE FROM email_custom_variables WHERE id = :id');
    $stmt->execute(['id' => $id]);
    flash_set('Variable eliminada.');
}
redirect(admin_url('email_variables.php'));

<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/media_library.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('media_library.php'));
}

$id = (int)($_POST['id'] ?? 0);
if ($id && media_delete($id)) {
    flash_set('Archivo eliminado.');
} else {
    flash_set('No se encontró el archivo.', 'error');
}
redirect(admin_url('media_library.php'));

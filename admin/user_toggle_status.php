<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('users.php'));
}

$db = get_db();
$id = (int)($_POST['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM admin_users WHERE id = :id');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    flash_set('Ese usuario no existe.', 'error');
} elseif ((int)$user['id'] === (int)current_admin()['id']) {
    flash_set('No puedes desactivar tu propia cuenta.', 'error');
} elseif ($user['status'] === 'active' && $user['role'] === 'super_admin' && admin_other_active_super_admins($user['id']) === 0) {
    flash_set('Debe quedar al menos un súper administrador activo.', 'error');
} else {
    $db->prepare("UPDATE admin_users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id")->execute(['id' => $id]);
    flash_set($user['status'] === 'active' ? 'Usuario desactivado: ya no puede entrar al panel.' : 'Usuario activado.');
}
redirect(admin_url('users.php'));

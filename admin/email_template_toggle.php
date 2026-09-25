<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('email_templates.php'));
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $db = get_db();
    $stmt = $db->prepare('SELECT status FROM email_templates WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetchColumn();

    if ($current !== false) {
        $new_status = $current === 'active' ? 'inactive' : 'active';
        $db->prepare('UPDATE email_templates SET status = :status WHERE id = :id')
           ->execute(['status' => $new_status, 'id' => $id]);
        flash_set($new_status === 'active' ? 'Plantilla activada.' : 'Plantilla desactivada.');
    }
}
redirect(admin_url('email_templates.php'));

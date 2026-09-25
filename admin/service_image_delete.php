<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$service_id = (int)($_POST['service_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('service_form.php?id=' . $service_id));
}

$image_id = (int)($_POST['image_id'] ?? 0);
if ($image_id) {
    $stmt = get_db()->prepare('DELETE FROM service_images WHERE id = :id AND service_id = :service_id');
    $stmt->execute(['id' => $image_id, 'service_id' => $service_id]);
    flash_set('Imagen eliminada de la galería.');
}
redirect(admin_url('service_form.php?id=' . $service_id));

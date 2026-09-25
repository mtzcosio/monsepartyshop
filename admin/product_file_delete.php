<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$product_id = (int)($_POST['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('product_form.php?id=' . $product_id));
}

$file_id = (int)($_POST['file_id'] ?? 0);
if ($file_id) {
    $db = get_db();
    $stmt = $db->prepare('SELECT file_name FROM product_files WHERE id = :id AND product_id = :product_id');
    $stmt->execute(['id' => $file_id, 'product_id' => $product_id]);
    $file = $stmt->fetch();

    if ($file) {
        $del = $db->prepare('DELETE FROM product_files WHERE id = :id');
        $del->execute(['id' => $file_id]);

        $path = __DIR__ . '/../uploads/downloads/' . basename($file['file_name']);
        if (file_exists($path)) {
            @unlink($path);
        }
        flash_set('Archivo eliminado.');
    }
}
redirect(admin_url('product_form.php?id=' . $product_id));

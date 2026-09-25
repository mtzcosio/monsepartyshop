<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('email_templates.php'));
}

$id = (int)($_POST['id'] ?? 0);
$db = get_db();
$stmt = $db->prepare('SELECT * FROM email_templates WHERE id = :id');
$stmt->execute(['id' => $id]);
$original = $stmt->fetch();

if (!$original) {
    flash_set('Plantilla no encontrada.', 'error');
    redirect(admin_url('email_templates.php'));
}

$exists_stmt = $db->prepare('SELECT COUNT(*) FROM email_templates WHERE code = :code');
$new_code = $original['code'] . '_COPIA';
$exists_stmt->execute(['code' => $new_code]);
if ((int)$exists_stmt->fetchColumn() > 0) {
    $new_code = $original['code'] . '_COPIA_' . strtoupper(bin2hex(random_bytes(2)));
}

$insert = $db->prepare(
    'INSERT INTO email_templates (name, code, type, status, sender_name, sender_email, subject, preheader, content_html)
     VALUES (:name, :code, :type, :status, :sender_name, :sender_email, :subject, :preheader, :content_html)'
);
$insert->execute([
    'name' => $original['name'] . ' (copia)',
    'code' => $new_code,
    'type' => $original['type'],
    'status' => 'draft',
    'sender_name' => $original['sender_name'],
    'sender_email' => $original['sender_email'],
    'subject' => $original['subject'],
    'preheader' => $original['preheader'],
    'content_html' => $original['content_html'],
]);
$new_id = (int)$db->lastInsertId();

flash_set('Plantilla duplicada como borrador.');
redirect(admin_url('email_template_form.php?id=' . $new_id));

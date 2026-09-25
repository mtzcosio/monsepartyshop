<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/email_variables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    flash_set('Solicitud inválida.', 'error');
    redirect(admin_url('email_templates.php'));
}

$id = (int)($_POST['id'] ?? 0);
$test_email = trim($_POST['test_email'] ?? '');
$came_from_editor = isset($_POST['inline_content_html']);
$redirect_url = $came_from_editor
    ? admin_url('email_template_form.php' . ($id ? '?id=' . $id : ''))
    : admin_url('email_templates.php');

if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    flash_set('Ingresa un correo válido para la prueba.', 'error');
    redirect($redirect_url);
}

$db = get_db();

if ($came_from_editor) {
    // Se envía lo que está actualmente en el formulario (aunque no se haya guardado todavía).
    $subject = trim($_POST['inline_subject'] ?? '');
    $sender_name = trim($_POST['inline_sender_name'] ?? '');
    $sender_email = trim($_POST['inline_sender_email'] ?? '');
    $content_html = $_POST['inline_content_html'] ?? '';
} else {
    $stmt = $db->prepare('SELECT * FROM email_templates WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $tpl = $stmt->fetch();
    if (!$tpl) {
        flash_set('Plantilla no encontrada.', 'error');
        redirect($redirect_url);
    }
    $subject = $tpl['subject'];
    $sender_name = $tpl['sender_name'];
    $sender_email = $tpl['sender_email'];
    $content_html = $tpl['content_html'];
}

if ($subject === '' || trim($content_html) === '') {
    flash_set('La plantilla necesita un asunto y contenido antes de poder probarla.', 'error');
    redirect($redirect_url);
}

$sender_name = $sender_name !== '' ? $sender_name : get_setting('store_name', 'Monse Party Shop');
$sender_email = $sender_email !== '' ? $sender_email : get_setting('email', 'no-reply@example.com');

$sample = email_variable_sample_data();
$rendered_subject = email_render_variables($subject, $sample);
$rendered_html = email_render_variables($content_html, $sample);

$result = send_html_email($test_email, '[PRUEBA] ' . $rendered_subject, $rendered_html);

flash_set($result['message'], $result['success'] ? 'success' : 'error');
redirect($redirect_url);

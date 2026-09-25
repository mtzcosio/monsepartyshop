<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM contact_messages WHERE id = :id');
$stmt->execute(['id' => $id]);
$msg = $stmt->fetch();

if (!$msg) {
    flash_set('Mensaje no encontrado.', 'error');
    redirect(admin_url('contact_messages.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    $status = in_array($_POST['status'] ?? '', ['new', 'read', 'archived'], true) ? $_POST['status'] : $msg['status'];
    $db->prepare('UPDATE contact_messages SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
    flash_set('Estado del mensaje actualizado.');
    redirect(admin_url('contact_message_detail.php?id=' . $id));
}

// Igual que un inbox: abrir un mensaje "Nuevo" lo marca como "Leído" automáticamente,
// para que el contador de notificaciones baje sin que el admin tenga que hacer nada extra.
if ($msg['status'] === 'new') {
    $db->prepare("UPDATE contact_messages SET status = 'read' WHERE id = :id")->execute(['id' => $id]);
    $msg['status'] = 'read';
}

$reason_options = contact_reason_options();
$preferred_options = contact_preferred_contact_options();
$status_labels = ['new' => 'Nuevo', 'read' => 'Leído', 'archived' => 'Archivado'];

$page_title = 'Mensaje de ' . $msg['full_name'];
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Mensaje de <?= e($msg['full_name']) ?></h1>
        <p class="page-subtitle">
            <span class="badge-status <?= $msg['status'] === 'archived' ? 'cancelled' : 'paid' ?>"><?= e($status_labels[$msg['status']] ?? $msg['status']) ?></span>
            &nbsp;·&nbsp; Recibido el <?= e(date('d/m/Y H:i', strtotime($msg['created_at']))) ?>
        </p>
    </div>
    <a href="<?= admin_url('contact_messages.php') ?>" class="btn btn-secondary btn-sm">← Volver al listado</a>
</div>

<div class="dashboard-grid">
    <div class="dashboard-main">
        <div class="section-card">
            <div class="section-card-head">
                <div><h3>Mensaje</h3></div>
            </div>
            <p style="white-space:pre-wrap;line-height:1.6;"><?= e($msg['message']) ?></p>
        </div>
    </div>

    <div class="dashboard-side">
        <div class="section-card">
            <div class="section-card-head"><div><h3>Datos de contacto</h3></div></div>
            <div style="font-size:13.5px;display:flex;flex-direction:column;gap:10px;">
                <div><strong style="color:var(--admin-text-muted);">Nombre:</strong><br><?= e($msg['full_name']) ?></div>
                <div><strong style="color:var(--admin-text-muted);">Correo:</strong><br><a href="mailto:<?= e($msg['email']) ?>?subject=<?= e(rawurlencode('Re: tu mensaje a ' . get_setting('store_name', 'Monse Party Shop'))) ?>"><?= e($msg['email']) ?></a></div>
                <?php if ($msg['phone']): ?><div><strong style="color:var(--admin-text-muted);">Teléfono:</strong><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $msg['phone'])) ?>"><?= e($msg['phone']) ?></a></div><?php endif; ?>
                <?php if ($msg['company']): ?><div><strong style="color:var(--admin-text-muted);">Empresa:</strong><br><?= e($msg['company']) ?></div><?php endif; ?>
                <div><strong style="color:var(--admin-text-muted);">Motivo:</strong><br><?= e($reason_options[$msg['reason']] ?? $msg['reason']) ?></div>
                <?php if ($msg['preferred_contact']): ?><div><strong style="color:var(--admin-text-muted);">Prefiere que le contacten por:</strong><br><?= e($preferred_options[$msg['preferred_contact']] ?? $msg['preferred_contact']) ?></div><?php endif; ?>
                <?php if ($msg['ip_address']): ?><div><strong style="color:var(--admin-text-muted);">IP:</strong><br><?= e($msg['ip_address']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="section-card">
            <div class="section-card-head"><div><h3>Estado</h3></div></div>
            <form method="post" style="display:flex;gap:10px;align-items:center;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <select name="status" class="form-control">
                    <?php foreach ($status_labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $msg['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/email_variables.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM email_templates WHERE id = :id');
$stmt->execute(['id' => $id]);
$tpl = $stmt->fetch();

if (!$tpl) {
    flash_set('Plantilla no encontrada.', 'error');
    redirect(admin_url('email_templates.php'));
}

$status_labels = ['active' => 'Activa', 'inactive' => 'Inactiva', 'draft' => 'Borrador'];
$sample = email_variable_sample_data();
$rendered_subject = email_render_variables($tpl['subject'], $sample);
$rendered_preheader = email_render_variables($tpl['preheader'], $sample);
$rendered_html = email_render_variables($tpl['content_html'], $sample);

$page_title = 'Ver plantilla';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0"><?= e($tpl['name']) ?></h1>
        <p class="page-subtitle">
            <code style="font-size:12.5px;"><?= e($tpl['code']) ?></code>
            &nbsp;·&nbsp; <span class="badge-type"><?= e($tpl['type']) ?></span>
            &nbsp;·&nbsp; <span class="badge-status <?= e($tpl['status']) ?>"><?= e($status_labels[$tpl['status']] ?? $tpl['status']) ?></span>
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="<?= admin_url('email_templates.php') ?>" class="btn btn-secondary btn-sm">← Volver al listado</a>
        <a href="<?= admin_url('email_template_form.php?id=' . (int)$tpl['id']) ?>" class="btn btn-primary btn-sm">Editar plantilla</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-main">
        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Vista previa</h3>
                    <p>Con datos de ejemplo, tal como la vería el destinatario.</p>
                </div>
            </div>
            <div class="email-preview-shell">
                <div class="email-preview-meta">
                    <div><strong>De:</strong> <?= e($tpl['sender_name'] ?: get_setting('store_name', 'Monse Party Shop')) ?> &lt;<?= e($tpl['sender_email'] ?: get_setting('email', '')) ?>&gt;</div>
                    <div><strong>Asunto:</strong> <?= e($rendered_subject) ?></div>
                    <?php if ($tpl['preheader']): ?><div><strong>Preencabezado:</strong> <?= e($rendered_preheader) ?></div><?php endif; ?>
                </div>
                <iframe class="email-preview-frame" srcdoc="<?= e($rendered_html) ?>"></iframe>
            </div>
        </div>
    </div>
    <div class="dashboard-side">
        <div class="section-card">
            <div class="section-card-head">
                <div>
                    <h3>Detalles</h3>
                </div>
            </div>
            <div style="font-size:13.5px;display:flex;flex-direction:column;gap:10px;">
                <div><strong style="color:var(--admin-text-muted);">Última modificación:</strong><br><?= e(date('d/m/Y H:i', strtotime($tpl['updated_at']))) ?></div>
                <div><strong style="color:var(--admin-text-muted);">Creada:</strong><br><?= e(date('d/m/Y H:i', strtotime($tpl['created_at']))) ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

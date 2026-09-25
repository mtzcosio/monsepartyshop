<?php
$page_title = 'Plantillas de correo';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/includes/email_variables.php';

$db = get_db();

$q = trim($_GET['q'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');
$estado = $_GET['estado'] ?? '';
$has_filters = ($q !== '' || $tipo !== '' || $estado !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($q !== '') {
    $where_sql .= ' AND (name LIKE :q OR code LIKE :q2)';
    $params['q'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
}
if ($tipo !== '') {
    $where_sql .= ' AND type = :tipo';
    $params['tipo'] = $tipo;
}
if (in_array($estado, ['draft', 'active', 'inactive'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}

$active_filter_count = ($q !== '' ? 1 : 0) + ($tipo !== '' ? 1 : 0) + (in_array($estado, ['draft', 'active', 'inactive'], true) ? 1 : 0);

$per_page = admin_get_per_page(20);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM email_templates' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM email_templates' . $where_sql . " ORDER BY updated_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$templates = $stmt->fetchAll();

$status_labels = ['active' => 'Activa', 'inactive' => 'Inactiva', 'draft' => 'Borrador'];
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Plantillas de correo</h1>
        <p class="page-subtitle">Administra las plantillas utilizadas para enviar correos electrónicos y notificaciones automáticas.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="<?= admin_url('email_variables.php') ?>" class="btn btn-secondary btn-sm">Variables</a>
        <a href="<?= admin_url('email_template_form.php') ?>" class="btn btn-primary btn-sm">+ Nueva plantilla</a>
    </div>
</div>

<div class="admin-topbar" style="margin-bottom:12px;">
    <div></div>
    <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
        <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
    </button>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('email_templates.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="q">Nombre o código</label>
            <input type="text" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar plantilla...">
        </div>
        <div class="form-group">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" class="form-control">
                <option value="">Todos</option>
                <?php foreach (email_template_types() as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="">Todos</option>
                <option value="active" <?= $estado === 'active' ? 'selected' : '' ?>>Activa</option>
                <option value="draft" <?= $estado === 'draft' ? 'selected' : '' ?>>Borrador</option>
                <option value="inactive" <?= $estado === 'inactive' ? 'selected' : '' ?>>Inactiva</option>
            </select>
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
    </div>
</form>

<div class="grid-toolbar">
    <div></div>
    <?php render_admin_records_count($total_records, 'plantillas'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead>
        <tr>
            <th>Nombre</th>
            <th class="col-hide-mobile">Código</th>
            <th class="col-hide-mobile">Tipo</th>
            <th class="col-hide-mobile">Asunto</th>
            <th>Estado</th>
            <th class="col-hide-mobile">Modificación</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($templates as $tpl): ?>
        <tr>
            <td><strong><?= e($tpl['name']) ?></strong></td>
            <td class="col-hide-mobile"><code style="font-size:12px;"><?= e($tpl['code']) ?></code></td>
            <td class="col-hide-mobile"><span class="badge-type"><?= e($tpl['type']) ?></span></td>
            <td class="col-hide-mobile"><?= e($tpl['subject']) ?></td>
            <td><span class="badge-status <?= e($tpl['status']) ?>"><?= e($status_labels[$tpl['status']] ?? $tpl['status']) ?></span></td>
            <td class="col-hide-mobile"><?= e(date('d/m/Y', strtotime($tpl['updated_at']))) ?></td>
            <td>
                <div class="row-actions">
                    <a class="icon-action-btn" href="<?= admin_url('email_template_view.php?id=' . (int)$tpl['id']) ?>" title="Ver plantilla">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    <a class="icon-action-btn" href="<?= admin_url('email_template_form.php?id=' . (int)$tpl['id']) ?>" title="Editar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <form method="post" action="<?= admin_url('email_template_duplicate.php') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                        <button type="submit" class="icon-action-btn" title="Duplicar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </form>
                    <button type="button" class="icon-action-btn js-send-test-btn" title="Enviar prueba"
                            data-id="<?= (int)$tpl['id'] ?>" data-name="<?= e($tpl['name']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                    <form method="post" action="<?= admin_url('email_template_toggle.php') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                        <button type="submit" class="icon-action-btn" title="<?= $tpl['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                        </button>
                    </form>
                    <button type="button" class="icon-action-btn danger js-delete-btn" title="Eliminar"
                            data-id="<?= (int)$tpl['id'] ?>" data-name="<?= e($tpl['name']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?>
        <tr><td colspan="7" class="text-center"><?= $has_filters ? 'No hay plantillas que coincidan con esos filtros.' : 'Aún no hay plantillas de correo. Crea la primera con "+ Nueva plantilla".' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php render_admin_pagination($page, $total_pages); ?>

<!-- Modal: enviar prueba -->
<div class="modal-overlay" id="sendTestModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Enviar correo de prueba</h3>
                <p id="sendTestModalSubtitle">Plantilla seleccionada</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('email_template_send_test.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="sendTestTemplateId" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="sendTestEmail">Correo electrónico de destino</label>
                    <input type="email" id="sendTestEmail" name="test_email" class="form-control" placeholder="ejemplo@correo.com" required>
                    <p class="settings-hint">Se sustituirán las variables con datos de ejemplo antes de enviarlo.</p>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Enviar prueba</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Eliminar plantilla</h3>
                <p id="deleteModalSubtitle">¿Eliminar esta plantilla?</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('email_template_delete.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteTemplateId" value="">
            <div class="modal-body">
                <p style="margin:0;font-size:13.5px;color:var(--admin-text-muted);">Esta acción no se puede deshacer. La plantilla dejará de estar disponible para su uso en el sistema.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary-color-dark);">Eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.js-send-test-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('sendTestTemplateId').value = btn.getAttribute('data-id');
        document.getElementById('sendTestModalSubtitle').textContent = btn.getAttribute('data-name');
        openModal('sendTestModal');
    });
});
document.querySelectorAll('.js-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('deleteTemplateId').value = btn.getAttribute('data-id');
        document.getElementById('deleteModalSubtitle').textContent = '¿Eliminar "' + btn.getAttribute('data-name') + '"?';
        openModal('deleteModal');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

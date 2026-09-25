<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/pagination.php';

$db = get_db();
$reason_options = contact_reason_options();

$nombre = trim($_GET['nombre'] ?? '');
$estado = $_GET['estado'] ?? '';
$motivo = $_GET['motivo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$has_filters = ($nombre !== '' || $estado !== '' || $motivo !== '' || $fecha_desde !== '' || $fecha_hasta !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($nombre !== '') {
    $where_sql .= ' AND (full_name LIKE :nombre OR email LIKE :nombre2)';
    $params['nombre'] = '%' . $nombre . '%';
    $params['nombre2'] = '%' . $nombre . '%';
}
if (in_array($estado, ['new', 'read', 'archived'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}
if (isset($reason_options[$motivo])) {
    $where_sql .= ' AND reason = :motivo';
    $params['motivo'] = $motivo;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $where_sql .= ' AND created_at >= :fecha_desde';
    $params['fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $where_sql .= ' AND created_at <= :fecha_hasta';
    $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$status_stmt = $db->query('SELECT status, COUNT(*) c FROM contact_messages GROUP BY status');
$status_counts = ['new' => 0, 'read' => 0, 'archived' => 0];
foreach ($status_stmt->fetchAll() as $row) { $status_counts[$row['status']] = (int)$row['c']; }
$total_all = array_sum($status_counts);

$per_page = admin_get_per_page(20);
$page = admin_current_page();
$count_stmt = $db->prepare('SELECT COUNT(*) FROM contact_messages' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM contact_messages' . $where_sql . " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$status_labels = ['new' => 'Nuevo', 'read' => 'Leído', 'archived' => 'Archivado'];

$page_title = 'Mensajes de contacto';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Mensajes de contacto</h1>
        <p class="page-subtitle"><?= $has_filters ? 'Mensajes que coinciden con los filtros aplicados.' : 'Mensajes recibidos desde el formulario de contacto de la tienda.' ?></p>
    </div>
    <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
    </button>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_all ?></div>
            <div class="stat-label">Total de mensajes</div>
        </div>
    </div>
    <a href="<?= admin_url('contact_messages.php?estado=new') ?>" class="stat-card stat-card-link">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['new'] ?></div>
            <div class="stat-label">Nuevos</div>
        </div>
    </a>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['read'] ?></div>
            <div class="stat-label">Leídos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['archived'] ?></div>
            <div class="stat-label">Archivados</div>
        </div>
    </div>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('contact_messages.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="nombre">Nombre o correo</label>
            <input type="text" id="nombre" name="nombre" class="form-control" value="<?= e($nombre) ?>" placeholder="nombre@ejemplo.com">
        </div>
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($status_labels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $estado === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($reason_options as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $motivo === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="fecha_desde">Desde</label>
            <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= e($fecha_desde) ?>">
        </div>
        <div class="form-group">
            <label for="fecha_hasta">Hasta</label>
            <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= e($fecha_hasta) ?>">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
    </div>
</form>

<div class="grid-toolbar">
    <div></div>
    <?php render_admin_records_count($total_records, 'mensajes'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Motivo</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($messages as $msg): ?>
        <tr>
            <td><?= e($msg['full_name']) ?></td>
            <td><?= e($msg['email']) ?></td>
            <td><?= e($reason_options[$msg['reason']] ?? $msg['reason']) ?></td>
            <td><span class="badge-status <?= $msg['status'] === 'new' ? 'pending' : ($msg['status'] === 'archived' ? 'cancelled' : 'paid') ?>"><?= e($status_labels[$msg['status']] ?? $msg['status']) ?></span></td>
            <td><?= e(date('d/m/Y H:i', strtotime($msg['created_at']))) ?></td>
            <td>
                <div class="row-actions">
                    <a class="icon-action-btn" href="<?= admin_url('contact_message_detail.php?id=' . (int)$msg['id']) ?>" title="Ver mensaje">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
        <tr><td colspan="6" class="text-center"><?= $has_filters ? 'No hay mensajes que coincidan con esos filtros.' : 'Aún no has recibido mensajes de contacto.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

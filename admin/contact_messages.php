<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_login();
require_once __DIR__ . '/includes/pagination.php';

$db = get_db();
$reason_options = contact_reason_options();
$contact_options = contact_preferred_contact_options();
$sort_options = ['recientes' => 'Más recientes primero', 'antiguos' => 'Más antiguos primero', 'nombre' => 'Nombre (A-Z)'];

$nombre = trim($_GET['nombre'] ?? '');
$texto = trim($_GET['texto'] ?? '');
$estado = $_GET['estado'] ?? '';
$motivo = $_GET['motivo'] ?? '';
$medio = $_GET['medio'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$orden = isset($sort_options[$_GET['orden'] ?? '']) ? $_GET['orden'] : 'recientes';

$where_sql = ' WHERE 1=1';
$params = [];
$active_filter_count = 0;

if ($nombre !== '') {
    $where_sql .= ' AND (full_name LIKE :nombre OR email LIKE :nombre2 OR phone LIKE :nombre3 OR company LIKE :nombre4)';
    $params['nombre'] = $params['nombre2'] = $params['nombre3'] = $params['nombre4'] = '%' . $nombre . '%';
    $active_filter_count++;
}
if ($texto !== '') {
    $where_sql .= ' AND message LIKE :texto';
    $params['texto'] = '%' . $texto . '%';
    $active_filter_count++;
}
if (in_array($estado, ['new', 'read', 'archived'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
    $active_filter_count++;
}
if (isset($reason_options[$motivo])) {
    $where_sql .= ' AND reason = :motivo';
    $params['motivo'] = $motivo;
    $active_filter_count++;
}
if (isset($contact_options[$medio])) {
    $where_sql .= ' AND preferred_contact = :medio';
    $params['medio'] = $medio;
    $active_filter_count++;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $where_sql .= ' AND created_at >= :fecha_desde';
    $params['fecha_desde'] = $fecha_desde . ' 00:00:00';
    $active_filter_count++;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $where_sql .= ' AND created_at <= :fecha_hasta';
    $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
    $active_filter_count++;
}
// El orden no cuenta como filtro, pero sí mantiene abierto el panel si se cambió.
$has_filters = $active_filter_count > 0 || $orden !== 'recientes';

$order_sql = ['recientes' => 'created_at DESC', 'antiguos' => 'created_at ASC', 'nombre' => 'full_name ASC, created_at DESC'][$orden];

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

$stmt = $db->prepare('SELECT * FROM contact_messages' . $where_sql . " ORDER BY $order_sql LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$status_labels = ['new' => 'Nuevo', 'read' => 'Leído', 'archived' => 'Archivado'];

$page_title = 'Mensajes de contacto';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Mensajes de contacto</h1>
        <p class="page-subtitle"><?= $active_filter_count ? 'Mensajes que coinciden con los filtros aplicados.' : 'Mensajes recibidos desde el formulario de contacto de la tienda.' ?></p>
    </div>
    <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
        <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
    </button>
</div>

<div class="stat-grid">
    <a href="<?= admin_url('contact_messages.php') ?>" class="stat-card stat-card-link">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_all ?></div>
            <div class="stat-label">Total de mensajes</div>
        </div>
    </a>
    <a href="<?= admin_url('contact_messages.php?estado=new') ?>" class="stat-card stat-card-link">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['new'] ?></div>
            <div class="stat-label">Nuevos</div>
        </div>
    </a>
    <a href="<?= admin_url('contact_messages.php?estado=read') ?>" class="stat-card stat-card-link">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['read'] ?></div>
            <div class="stat-label">Leídos</div>
        </div>
    </a>
    <a href="<?= admin_url('contact_messages.php?estado=archived') ?>" class="stat-card stat-card-link">
        <div class="stat-icon pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$status_counts['archived'] ?></div>
            <div class="stat-label">Archivados</div>
        </div>
    </a>
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
            <label for="nombre">Nombre, correo, teléfono o empresa</label>
            <input type="text" id="nombre" name="nombre" class="form-control" value="<?= e($nombre) ?>" placeholder="Buscar remitente...">
        </div>
        <div class="form-group">
            <label for="texto">Texto del mensaje</label>
            <input type="text" id="texto" name="texto" class="form-control" value="<?= e($texto) ?>" placeholder="Ej. cotización, boda, factura...">
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
            <label for="medio">Medio de contacto preferido</label>
            <select id="medio" name="medio" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($contact_options as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $medio === $value ? 'selected' : '' ?>><?= e($label) ?></option>
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
        <div class="form-group">
            <label for="orden">Ordenar por</label>
            <select id="orden" name="orden" class="form-control">
                <?php foreach ($sort_options as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $orden === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
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
    <thead><tr><th>Nombre</th><th>Contacto</th><th>Motivo</th><th>Prefiere</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($messages as $msg): ?>
        <tr>
            <td><?= e($msg['full_name']) ?></td>
            <td>
                <?= e($msg['email']) ?>
                <?php if ($msg['phone']): ?><br><span style="font-size:12.5px;color:var(--admin-text-muted);"><?= e($msg['phone']) ?></span><?php endif; ?>
            </td>
            <td><?= e($reason_options[$msg['reason']] ?? $msg['reason']) ?></td>
            <td><?= e($contact_options[$msg['preferred_contact']] ?? ($msg['preferred_contact'] ?: '—')) ?></td>
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
        <tr><td colspan="7" class="text-center"><?= $active_filter_count ? 'No hay mensajes que coincidan con esos filtros.' : 'Aún no has recibido mensajes de contacto.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

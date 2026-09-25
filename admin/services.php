<?php
$page_title = 'Servicios';
require_once __DIR__ . '/includes/admin_header.php';

$db = get_db();

$nombre = trim($_GET['nombre'] ?? '');
$estado = $_GET['estado'] ?? '';
$has_filters = ($nombre !== '' || $estado !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($nombre !== '') {
    $where_sql .= ' AND name LIKE :nombre';
    $params['nombre'] = '%' . $nombre . '%';
}
if (in_array($estado, ['active', 'inactive'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}

$per_page = admin_get_per_page(20);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM services' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM services' . $where_sql . " ORDER BY sort_order ASC, created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

<?php $active_filter_count = ($nombre !== '' ? 1 : 0) + (in_array($estado, ['active', 'inactive'], true) ? 1 : 0); ?>

<div class="admin-topbar">
    <h1 class="mt-0">Servicios</h1>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
            <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
        </button>
        <a href="<?= admin_url('service_form.php') ?>" class="btn btn-primary btn-sm">+ Nuevo servicio</a>
    </div>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('services.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" class="form-control" value="<?= e($nombre) ?>" placeholder="Buscar por nombre...">
        </div>
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="">Todos</option>
                <option value="active" <?= $estado === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $estado === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
    </div>
</form>

<div class="grid-toolbar">
    <div></div>
    <?php render_admin_records_count($total_records, 'servicios'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Precio desde</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($services as $s): ?>
        <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= $s['price_from'] !== null ? format_price($s['price_from']) : '—' ?></td>
            <td><span class="badge-status <?= e($s['status']) ?>"><?= $s['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span></td>
            <td>
                <div class="row-actions">
                    <a class="icon-action-btn" href="<?= admin_url('service_form.php?id=' . (int)$s['id']) ?>" title="Editar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <form method="post" action="<?= admin_url('service_toggle_status.php') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="icon-action-btn" title="<?= $s['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                        </button>
                    </form>
                    <button type="button" class="icon-action-btn danger js-delete-btn" title="Eliminar"
                            data-id="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$services): ?>
        <tr><td colspan="4" class="text-center"><?= $has_filters ? 'No hay servicios que coincidan con esos filtros.' : 'Aún no hay servicios. Crea el primero con "+ Nuevo servicio".' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<!-- Modal: confirmar eliminación -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Eliminar servicio</h3>
                <p id="deleteModalSubtitle">¿Eliminar este servicio?</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('service_delete.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteServiceId" value="">
            <div class="modal-body">
                <p style="margin:0;font-size:13.5px;color:var(--admin-text-muted);">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" style="background:var(--primary-color-dark);">Eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.js-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('deleteServiceId').value = btn.getAttribute('data-id');
        document.getElementById('deleteModalSubtitle').textContent = '¿Eliminar "' + btn.getAttribute('data-name') + '"?';
        openModal('deleteModal');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

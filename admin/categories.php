<?php
$page_title = 'Categorías';
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
    $where_sql .= ' AND is_active = :estado';
    $params['estado'] = $estado === 'active' ? 1 : 0;
}

$per_page = admin_get_per_page(20);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM categories' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM categories' . $where_sql . " ORDER BY sort_order ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<?php $active_filter_count = ($nombre !== '' ? 1 : 0) + (in_array($estado, ['active', 'inactive'], true) ? 1 : 0); ?>

<div class="admin-topbar">
    <h1 class="mt-0">Categorías</h1>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
            <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
        </button>
        <a href="<?= admin_url('category_form.php') ?>" class="btn btn-primary btn-sm">+ Nueva categoría</a>
    </div>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('categories.php') ?>" class="filter-clear-link">
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
                <option value="active" <?= $estado === 'active' ? 'selected' : '' ?>>Activa</option>
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
    <?php render_admin_records_count($total_records, 'categorías'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Icono</th><th>Nombre</th><th>Slug</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td style="font-size:22px;"><?= e($cat['icon']) ?></td>
            <td><?= e($cat['name']) ?></td>
            <td><?= e($cat['slug']) ?></td>
            <td><?= (int)$cat['sort_order'] ?></td>
            <td><?= $cat['is_active'] ? '✅ Activa' : '⛔ Inactiva' ?></td>
            <td>
                <a href="<?= admin_url('category_form.php?id=' . (int)$cat['id']) ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" action="<?= admin_url('category_delete.php') ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar esta categoría? Los productos quedarán sin categoría.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?>
        <tr><td colspan="6" class="text-center"><?= $has_filters ? 'No hay categorías que coincidan con esos filtros.' : 'Aún no hay categorías.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

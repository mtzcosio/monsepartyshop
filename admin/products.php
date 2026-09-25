<?php
$page_title = 'Productos';
require_once __DIR__ . '/includes/admin_header.php';

$db = get_db();
$all_categories = $db->query('SELECT * FROM categories ORDER BY sort_order ASC')->fetchAll();

$nombre = trim($_GET['nombre'] ?? '');
$categoria = $_GET['categoria'] ?? '';
$estado = $_GET['estado'] ?? '';
$has_filters = ($nombre !== '' || $categoria !== '' || $estado !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($nombre !== '') {
    $where_sql .= ' AND p.name LIKE :nombre';
    $params['nombre'] = '%' . $nombre . '%';
}
if ($categoria !== '') {
    $where_sql .= ' AND EXISTS (SELECT 1 FROM product_categories pc2
                                JOIN categories c2 ON c2.id = pc2.category_id
                                WHERE pc2.product_id = p.id AND c2.slug = :categoria)';
    $params['categoria'] = $categoria;
}
if (in_array($estado, ['active', 'inactive'], true)) {
    $where_sql .= ' AND p.status = :estado';
    $params['estado'] = $estado;
}

$per_page = admin_get_per_page(20);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM products p' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare(
    "SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_name FROM products p
     LEFT JOIN product_categories pc ON pc.product_id = p.id
     LEFT JOIN categories c ON c.id = pc.category_id"
     . $where_sql .
    " GROUP BY p.id
     ORDER BY p.created_at DESC
     LIMIT $per_page OFFSET $offset"
);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<?php $active_filter_count = ($nombre !== '' ? 1 : 0) + ($categoria !== '' ? 1 : 0) + (in_array($estado, ['active', 'inactive'], true) ? 1 : 0); ?>

<div class="admin-topbar">
    <h1 class="mt-0">Productos</h1>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
            <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
        </button>
        <a href="<?= admin_url('product_form.php') ?>" class="btn btn-primary btn-sm">+ Nuevo producto</a>
    </div>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('products.php') ?>" class="filter-clear-link">
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
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= e($cat['slug']) ?>" <?= $categoria === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['icon'] . ' ' . $cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
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
    <?php render_admin_records_count($total_records, 'productos'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Etiquetas</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= e($p['name']) ?></td>
            <td><?= e($p['category_name'] ?: '—') ?></td>
            <td><?= format_price($p['price']) ?></td>
            <td>
                <?php if ($p['is_new']): ?>🆕<?php endif; ?>
                <?php if ($p['is_bestseller']): ?>⭐<?php endif; ?>
                <?php if ($p['is_kit']): ?>📦<?php endif; ?>
                <?php if ($p['is_offer']): ?>🔥<?php endif; ?>
            </td>
            <td><?= $p['status'] === 'active' ? '✅ Activo' : '⛔ Inactivo' ?></td>
            <td>
                <a href="<?= admin_url('product_form.php?id=' . (int)$p['id']) ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" action="<?= admin_url('product_delete.php') ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
        <tr><td colspan="6" class="text-center"><?= $has_filters ? 'No hay productos que coincidan con esos filtros.' : 'Aún no hay productos.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

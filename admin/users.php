<?php
$page_title = 'Usuarios';
require_once __DIR__ . '/includes/admin_header.php';

$db = get_db();
$roles = admin_roles();
$me = current_admin();

$nombre = trim($_GET['nombre'] ?? '');
$rol = $_GET['rol'] ?? '';
$estado = $_GET['estado'] ?? '';
$has_filters = ($nombre !== '' || $rol !== '' || $estado !== '');

$where_sql = ' WHERE 1=1';
$params = [];

if ($nombre !== '') {
    $where_sql .= ' AND (name LIKE :nombre OR email LIKE :nombre OR phone LIKE :nombre OR username LIKE :nombre)';
    $params['nombre'] = '%' . $nombre . '%';
}
if (isset($roles[$rol])) {
    $where_sql .= ' AND role = :rol';
    $params['rol'] = $rol;
}
if (in_array($estado, ['active', 'inactive'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}

$per_page = admin_get_per_page(20);
$page = admin_current_page();

$count_stmt = $db->prepare('SELECT COUNT(*) FROM admin_users' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM admin_users' . $where_sql . " ORDER BY FIELD(role, 'super_admin', 'admin', 'editor'), name ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$active_filter_count = ($nombre !== '' ? 1 : 0) + (isset($roles[$rol]) ? 1 : 0) + (in_array($estado, ['active', 'inactive'], true) ? 1 : 0);
?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Usuarios</h1>
        <p class="page-subtitle">Cuentas con acceso al panel administrativo y su rol.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
            <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
        </button>
        <a href="<?= admin_url('user_form.php') ?>" class="btn btn-primary btn-sm">+ Nuevo usuario</a>
    </div>
</div>

<form method="get" class="filter-panel" id="filtersCard" style="<?= $has_filters ? '' : 'display:none;' ?>">
    <div class="filter-panel-head">
        <div class="filter-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            Filtrar resultados
        </div>
        <?php if ($has_filters): ?>
            <a href="<?= admin_url('users.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="nombre">Buscar</label>
            <input type="text" id="nombre" name="nombre" class="form-control" value="<?= e($nombre) ?>" placeholder="Nombre, correo o teléfono...">
        </div>
        <div class="form-group">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($roles as $key => $role): ?>
                    <option value="<?= e($key) ?>" <?= $rol === $key ? 'selected' : '' ?>><?= e($role['label']) ?></option>
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
    <?php render_admin_records_count($total_records, 'usuarios'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Correo / teléfono</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <?php $is_me = (int)$u['id'] === (int)$me['id']; ?>
        <tr>
            <td>
                <?= e($u['name'] ?: $u['username']) ?>
                <?php if ($is_me): ?><span style="font-size:11.5px;color:var(--admin-text-muted);">(tú)</span><?php endif; ?>
            </td>
            <td>
                <?= e($u['email'] ?: '—') ?>
                <?php if ($u['phone']): ?><br><span style="font-size:12.5px;color:var(--admin-text-muted);"><?= e($u['phone']) ?></span><?php endif; ?>
            </td>
            <td><?= e(admin_role_label($u['role'])) ?></td>
            <td><span class="badge-status <?= e($u['status']) ?>"><?= $u['status'] === 'active' ? 'Activo' : 'Inactivo' ?></span></td>
            <td><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : 'Nunca' ?></td>
            <td>
                <div class="row-actions">
                    <a class="icon-action-btn" href="<?= admin_url($is_me ? 'profile.php' : 'user_form.php?id=' . (int)$u['id']) ?>" title="Editar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <?php if (!$is_me): ?>
                    <form method="post" action="<?= admin_url('user_toggle_status.php') ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="icon-action-btn" title="<?= $u['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                        </button>
                    </form>
                    <button type="button" class="icon-action-btn danger js-delete-btn" title="Eliminar"
                            data-id="<?= (int)$u['id'] ?>" data-name="<?= e($u['name'] ?: $u['username']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
        <tr><td colspan="6" class="text-center"><?= $has_filters ? 'No hay usuarios que coincidan con esos filtros.' : 'Aún no hay usuarios.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<div class="card-box" style="margin-top:24px;">
    <h3 style="margin-top:0;">¿Qué puede hacer cada rol?</h3>
    <?php foreach ($roles as $role): ?>
        <p style="margin:0 0 8px;font-size:13.5px;"><strong><?= e($role['label']) ?>:</strong> <?= e($role['description']) ?></p>
    <?php endforeach; ?>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>Eliminar usuario</h3>
                <p id="deleteModalSubtitle">¿Eliminar este usuario?</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="post" action="<?= admin_url('user_delete.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteUserId" value="">
            <div class="modal-body">
                <p style="margin:0;font-size:13.5px;color:var(--admin-text-muted);">La persona perderá el acceso al panel de inmediato. Esta acción no se puede deshacer; si solo quieres quitarle el acceso temporalmente, mejor desactívala.</p>
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
        document.getElementById('deleteUserId').value = btn.getAttribute('data-id');
        document.getElementById('deleteModalSubtitle').textContent = '¿Eliminar a "' + btn.getAttribute('data-name') + '"?';
        openModal('deleteModal');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

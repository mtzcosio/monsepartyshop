<?php
$page_title = 'Pedidos';
require_once __DIR__ . '/includes/admin_header.php';

$db = get_db();

$codigo = trim($_GET['codigo'] ?? '');
$cliente = trim($_GET['cliente'] ?? '');
$estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$where_sql = ' WHERE 1=1';
$params = [];

if ($codigo !== '') {
    $where_sql .= ' AND order_code LIKE :codigo';
    $params['codigo'] = '%' . $codigo . '%';
}
if ($cliente !== '') {
    $where_sql .= ' AND (customer_name LIKE :cliente OR customer_email LIKE :cliente2)';
    $params['cliente'] = '%' . $cliente . '%';
    $params['cliente2'] = '%' . $cliente . '%';
}
if (in_array($estado, ['pending', 'paid', 'cancelled'], true)) {
    $where_sql .= ' AND status = :estado';
    $params['estado'] = $estado;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $where_sql .= ' AND created_at >= :fecha_desde';
    $params['fecha_desde'] = $fecha_desde . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $where_sql .= ' AND created_at <= :fecha_hasta';
    $params['fecha_hasta'] = $fecha_hasta . ' 23:59:59';
}

$has_filters = ($codigo !== '' || $cliente !== '' || $estado !== '' || $fecha_desde !== '' || $fecha_hasta !== '');

$per_page = admin_get_per_page(20);
$page = admin_current_page();
$count_stmt = $db->prepare('SELECT COUNT(*) FROM orders' . $where_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();

/* Indicadores de arriba: se calculan sobre el mismo WHERE de los filtros aplicados,
   así que cambian junto con el listado en vez de mostrar totales globales. */
$stats_stmt = $db->prepare('SELECT status, COUNT(*) c, COALESCE(SUM(total),0) s FROM orders' . $where_sql . ' GROUP BY status');
$stats_stmt->execute($params);
$order_stats = ['paid' => ['count' => 0, 'sum' => 0.0], 'pending' => ['count' => 0, 'sum' => 0.0], 'cancelled' => ['count' => 0, 'sum' => 0.0]];
foreach ($stats_stmt->fetchAll() as $row) {
    $order_stats[$row['status']] = ['count' => (int)$row['c'], 'sum' => (float)$row['s']];
}

$total_pages = max(1, (int)ceil($total_records / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare('SELECT * FROM orders' . $where_sql . " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<?php $active_filter_count = ($codigo !== '' ? 1 : 0) + ($cliente !== '' ? 1 : 0) + (in_array($estado, ['pending', 'paid', 'cancelled'], true) ? 1 : 0) + ($fecha_desde !== '' ? 1 : 0) + ($fecha_hasta !== '' ? 1 : 0); ?>

<div class="admin-topbar">
    <div>
        <h1 class="mt-0">Pedidos</h1>
        <p class="page-subtitle"><?= $has_filters ? 'Resumen de los pedidos que coinciden con los filtros aplicados.' : 'Resumen de todos los pedidos.' ?></p>
    </div>
    <button type="button" class="btn-filter-toggle" data-toggle-target="filtersCard" data-label-open="Ocultar filtros" data-label-closed="Mostrar filtros">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        <span class="filter-toggle-label"><?= $has_filters ? 'Ocultar filtros' : 'Mostrar filtros' ?></span>
        <?php if ($active_filter_count > 0): ?><span class="filter-count-badge"><?= $active_filter_count ?></span><?php endif; ?>
    </button>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$total_records ?></div>
            <div class="stat-label">Total de pedidos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$order_stats['paid']['count'] ?></div>
            <div class="stat-label">Pagados</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$order_stats['pending']['count'] ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon pink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= (int)$order_stats['cancelled']['count'] ?></div>
            <div class="stat-label">Cancelados</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-body">
            <div class="stat-value"><?= format_price($order_stats['paid']['sum']) ?></div>
            <div class="stat-label">Monto pagado</div>
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
            <a href="<?= admin_url('orders.php') ?>" class="filter-clear-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Limpiar filtros
            </a>
        <?php endif; ?>
    </div>
    <div class="filter-grid">
        <div class="form-group">
            <label for="codigo">Código de pedido</label>
            <input type="text" id="codigo" name="codigo" class="form-control" value="<?= e($codigo) ?>" placeholder="MPS-...">
        </div>
        <div class="form-group">
            <label for="cliente">Cliente (nombre o correo)</label>
            <input type="text" id="cliente" name="cliente" class="form-control" value="<?= e($cliente) ?>" placeholder="nombre@ejemplo.com">
        </div>
        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="">Todos</option>
                <option value="pending" <?= $estado === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                <option value="paid" <?= $estado === 'paid' ? 'selected' : '' ?>>Pagado</option>
                <option value="cancelled" <?= $estado === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
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
    <?php render_admin_records_count($total_records, 'pedidos'); ?>
    <?php render_admin_per_page_select($per_page); ?>
</div>

<table class="admin-table">
    <thead><tr><th>Código</th><th>Cliente</th><th>Correo</th><th>Total</th><th>Pago</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
        <?php $payment_labels = ['card' => 'Tarjeta', 'oxxo_cash' => 'OXXO', 'spei' => 'SPEI']; ?>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= e($order['order_code']) ?></td>
            <td><?= e($order['customer_name']) ?></td>
            <td><?= e($order['customer_email']) ?></td>
            <td><?= format_price($order['total']) ?></td>
            <td><?= e($payment_labels[$order['payment_method']] ?? 'Tarjeta') ?></td>
            <td><span class="badge-status <?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
            <td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="<?= admin_url('order_detail.php?id=' . (int)$order['id']) ?>" class="btn btn-secondary btn-sm">Ver</a>
                <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="<?= admin_url('order_detail.php?id=' . (int)$order['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" name="send_payment_reminder" class="btn btn-secondary btn-sm" title="Enviar recordatorio de pago">⏰ Recordar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td colspan="8" class="text-center"><?= $has_filters ? 'No hay pedidos que coincidan con esos filtros.' : 'Aún no hay pedidos.' ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php render_admin_pagination($page, $total_pages); ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
